<?php

declare(strict_types=1);

namespace Tests\SharedKernel\Infrastructure\Storage;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Exception;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Storage\StorageException;
use SharedKernel\Infrastructure\Storage\S3Storage;

class S3StorageTest extends TestCase
{
    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var S3Client|MockObject */
    private $s3Client;

    private string $bucket;

    private S3Storage $storage;

    protected function setUp(): void
    {
        if (!class_exists(S3Client::class)) {
            $this->markTestSkipped('AWS SDK is not installed in this test suite');
        }

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->s3Client = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['putObject', 'getObject', 'deleteObject', 'headObject', 'copyObject'])
            ->getMock();
        $this->bucket = 'test-bucket';
        $this->storage = new S3Storage($this->logger, $this->s3Client, $this->bucket);
    }

    // -- put --

    public function testPutUploadsToS3(): void
    {
        $path = 'test/path.txt';
        $stream = Utils::streamFor('test content');

        $this->s3Client->expects($this->once())
            ->method('putObject')
            ->willReturnCallback(function ($params) use ($path, $stream) {
                $this->assertEquals($this->bucket, $params['Bucket']);
                $this->assertEquals($path, $params['Key']);
                $this->assertSame($stream, $params['Body']);
            });

        $this->storage->put($path, $stream);
    }

    public function testPutWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->put('', Utils::streamFor('content'));
    }

    public function testPutThrowsStorageExceptionWithPreviousOnFailure(): void
    {
        $original = new Exception('S3 error');

        $this->s3Client->expects($this->once())
            ->method('putObject')
            ->willThrowException($original);

        $this->logger->expects($this->once())
            ->method('error');

        try {
            $this->storage->put('test/path.txt', Utils::streamFor('content'));
            $this->fail('Expected StorageException');
        } catch (StorageException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    // -- get --

    public function testGetReturnsStream(): void
    {
        $path = 'test/path.txt';
        $stream = Utils::streamFor('test content');

        $result = $this->createMock(Result::class);
        $result->expects($this->once())
            ->method('offsetGet')
            ->willReturn($stream);

        $this->s3Client->expects($this->once())
            ->method('getObject')
            ->willReturnCallback(function ($params) use ($path, $result) {
                $this->assertEquals($this->bucket, $params['Bucket']);
                $this->assertEquals($path, $params['Key']);
                return $result;
            });

        $resultStream = $this->storage->get($path);
        $this->assertSame($stream, $resultStream);
    }

    public function testGetWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->get('');
    }

    public function testGetThrowsStorageExceptionOnFailure(): void
    {
        $this->s3Client->expects($this->once())
            ->method('getObject')
            ->willThrowException(new Exception('S3 error'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(StorageException::class);
        $this->storage->get('test/path.txt');
    }

    // -- delete --

    public function testDeleteChecksExistenceThenDeletes(): void
    {
        $path = 'test/path.txt';

        $this->s3Client->expects($this->once())
            ->method('headObject')
            ->willReturn(new Result());

        $this->s3Client->expects($this->once())
            ->method('deleteObject')
            ->willReturnCallback(function ($params) use ($path) {
                $this->assertEquals($this->bucket, $params['Bucket']);
                $this->assertEquals($path, $params['Key']);
            });

        $this->storage->delete($path);
    }

    public function testDeleteThrowsFileNotFoundWhenObjectDoesNotExist(): void
    {
        $s3Exception = new S3Exception(
            'Not Found',
            $this->createCommandMock(),
            ['code' => 'NotFound', 'response' => new Response(404)]
        );

        $this->s3Client->expects($this->once())
            ->method('headObject')
            ->willThrowException($s3Exception);

        $this->s3Client->expects($this->never())
            ->method('deleteObject');

        $this->expectException(StorageException::class);
        $this->storage->delete('nonexistent/path.txt');
    }

    public function testDeleteWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->delete('');
    }

    public function testDeleteThrowsStorageExceptionOnS3Failure(): void
    {
        $this->s3Client->expects($this->once())
            ->method('headObject')
            ->willReturn(new Result());

        $this->s3Client->expects($this->once())
            ->method('deleteObject')
            ->willThrowException(new Exception('S3 error'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(StorageException::class);
        $this->storage->delete('test/path.txt');
    }

    // -- exists --

    public function testExistsReturnsTrueWhenObjectExists(): void
    {
        $this->s3Client->expects($this->once())
            ->method('headObject')
            ->willReturn(new Result());

        $this->assertTrue($this->storage->exists('test/path.txt'));
    }

    public function testExistsReturnsFalseOn404(): void
    {
        $s3Exception = new S3Exception(
            'Not Found',
            $this->createCommandMock(),
            ['code' => 'NotFound', 'response' => new Response(404)]
        );

        $this->s3Client->expects($this->once())
            ->method('headObject')
            ->willThrowException($s3Exception);

        $this->assertFalse($this->storage->exists('nonexistent/path.txt'));
    }

    public function testExistsRethrowsNon404S3Exception(): void
    {
        $s3Exception = new S3Exception(
            'Forbidden',
            $this->createCommandMock(),
            ['code' => 'Forbidden', 'response' => new Response(403)]
        );

        $this->s3Client->expects($this->once())
            ->method('headObject')
            ->willThrowException($s3Exception);

        $this->expectException(S3Exception::class);
        $this->storage->exists('test/path.txt');
    }

    public function testExistsWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->exists('');
    }

    // -- getSize --

    public function testGetSizeReturnsContentLength(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())
            ->method('offsetGet')
            ->with('ContentLength')
            ->willReturn(1024);

        $this->s3Client->expects($this->once())
            ->method('headObject')
            ->willReturn($result);

        $this->assertEquals(1024, $this->storage->getSize('test/path.txt'));
    }

    public function testGetSizeWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->getSize('');
    }

    public function testGetSizeThrowsStorageExceptionOnFailure(): void
    {
        $this->s3Client->expects($this->once())
            ->method('headObject')
            ->willThrowException(new Exception('S3 error'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(StorageException::class);
        $this->storage->getSize('test/path.txt');
    }

    // -- copy --

    public function testCopyEncodesSourcePath(): void
    {
        $from = 'test/source file.txt';
        $to = 'test/destination.txt';

        $this->s3Client->expects($this->once())
            ->method('copyObject')
            ->willReturnCallback(function ($params) use ($to) {
                $this->assertEquals($this->bucket, $params['Bucket']);
                $this->assertEquals($to, $params['Key']);
                $this->assertEquals('test-bucket/test/source%20file.txt', $params['CopySource']);
            });

        $this->storage->copy($from, $to);
    }

    public function testCopyWithEmptyFromPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->copy('', 'destination');
    }

    public function testCopyWithEmptyToPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->copy('source', '');
    }

    public function testCopyThrowsStorageExceptionOnFailure(): void
    {
        $this->s3Client->expects($this->once())
            ->method('copyObject')
            ->willThrowException(new Exception('S3 error'));

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(StorageException::class);
        $this->storage->copy('test/source.txt', 'test/destination.txt');
    }

    // -- path traversal --

    public function testPutRejectsPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must not contain ".." segments');
        $this->storage->put('../../etc/passwd', Utils::streamFor('content'));
    }

    public function testGetRejectsPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must not contain ".." segments');
        $this->storage->get('uploads/../../../etc/passwd');
    }

    public function testDeleteRejectsPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->delete('../secret/file.txt');
    }

    public function testExistsRejectsPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->exists('foo/../../bar');
    }

    // -- helper --

    private function createCommandMock(): CommandInterface
    {
        return $this->createMock(CommandInterface::class);
    }
}
