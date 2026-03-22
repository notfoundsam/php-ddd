<?php

declare(strict_types=1);

namespace Tests\SharedKernel\Infrastructure\Storage;

use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Storage\StorageException;
use SharedKernel\Infrastructure\Storage\LocalStorage;

class LocalStorageTest extends TestCase
{
    /** @var LoggerInterface|MockObject */
    private $logger;

    private LocalStorage $storage;

    private string $testDir;

    protected function setUp(): void
    {
        if (!class_exists(Utils::class)) {
            $this->markTestSkipped('GuzzleHttp PSR-7 is not installed in this test suite');
        }

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->storage = new LocalStorage($this->logger);
        $this->testDir = '/app/storage/';
    }

    protected function tearDown(): void
    {
        $testPath = $this->testDir . 'local_test';
        if (!is_dir($testPath)) {
            return;
        }

        $this->removeDirectory($testPath);
    }

    // -- put & get --

    public function testPutAndGetRoundTrip(): void
    {
        $path = 'local_test/roundtrip.txt';
        $content = 'hello world';

        $this->storage->putString($path, $content);

        $result = $this->storage->getString($path);
        $this->assertEquals($content, $result);
    }

    public function testPutOverwritesExistingFile(): void
    {
        $path = 'local_test/overwrite.txt';

        $this->storage->putString($path, 'first');
        $this->storage->putString($path, 'second');

        $this->assertEquals('second', $this->storage->getString($path));
    }

    public function testPutWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->putString('', 'content');
    }

    // -- get --

    public function testGetThrowsFileNotFoundForMissingFile(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->get('local_test/nonexistent.txt');
    }

    public function testGetWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->get('');
    }

    // -- delete --

    public function testDeleteRemovesFile(): void
    {
        $path = 'local_test/to_delete.txt';
        $this->storage->putString($path, 'content');

        $this->assertTrue($this->storage->exists($path));

        $this->storage->delete($path);

        $this->assertFalse($this->storage->exists($path));
    }

    public function testDeleteThrowsFileNotFoundForMissingFile(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->delete('local_test/nonexistent.txt');
    }

    public function testDeleteWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->delete('');
    }

    // -- exists --

    public function testExistsReturnsTrueForExistingFile(): void
    {
        $path = 'local_test/exists.txt';
        $this->storage->putString($path, 'content');

        $this->assertTrue($this->storage->exists($path));
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->storage->exists('local_test/missing.txt'));
    }

    public function testExistsWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->exists('');
    }

    // -- getSize --

    public function testGetSizeReturnsFileSize(): void
    {
        $path = 'local_test/size.txt';
        $content = 'twelve chars';
        $this->storage->putString($path, $content);

        $this->assertEquals(strlen($content), $this->storage->getSize($path));
    }

    public function testGetSizeThrowsFileNotFoundForMissingFile(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->getSize('local_test/nonexistent.txt');
    }

    public function testGetSizeWithEmptyPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->getSize('');
    }

    // -- copy --

    public function testCopyCopiesFile(): void
    {
        $from = 'local_test/original.txt';
        $to = 'local_test/copied.txt';
        $content = 'copy me';

        $this->storage->putString($from, $content);
        $this->storage->copy($from, $to);

        $this->assertEquals($content, $this->storage->getString($to));
        $this->assertTrue($this->storage->exists($from));
    }

    public function testCopyThrowsFileNotFoundForMissingSource(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->copy('local_test/missing.txt', 'local_test/dest.txt');
    }

    public function testCopyWithEmptyPathsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->copy('', 'dest');
    }

    // -- path traversal --

    public function testPutRejectsPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must not contain ".." segments');
        $this->storage->putString('../../etc/passwd', 'content');
    }

    public function testGetRejectsPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
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

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
