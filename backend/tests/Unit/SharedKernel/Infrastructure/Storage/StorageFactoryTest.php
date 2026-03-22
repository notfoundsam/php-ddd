<?php

declare(strict_types=1);

namespace Tests\SharedKernel\Infrastructure\Storage;

use Aws\S3\S3Client;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\Storage\LocalStorage;
use SharedKernel\Infrastructure\Storage\S3ClientFactory;
use SharedKernel\Infrastructure\Storage\S3Storage;
use SharedKernel\Infrastructure\Storage\StorageFactory;

class StorageFactoryTest extends TestCase
{
    /** @var S3ClientFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $s3ClientFactory;

    /** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    protected function setUp(): void
    {
        if (!class_exists(S3Client::class)) {
            $this->markTestSkipped('AWS SDK is not installed in this test suite');
        }

        $this->s3ClientFactory = $this->createMock(S3ClientFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        putenv('AWS_BUCKET');
        putenv('AWS_DEFAULT_REGION');
    }

    public function testCreateReturnsLocalStorageInDevelopment(): void
    {
        $factory = $this->createFactory('development');

        $result = $factory->create();

        $this->assertInstanceOf(LocalStorage::class, $result);
    }

    public function testCreateReturnsS3StorageInProduction(): void
    {
        $factory = $this->createFactory('production');
        $this->setEnv();

        $s3Client = $this->createMock(S3Client::class);
        $this->s3ClientFactory->expects($this->once())
            ->method('create')
            ->with('default', 'ap-northeast-1')
            ->willReturn($s3Client);

        $result = $factory->create();

        $this->assertInstanceOf(S3Storage::class, $result);
    }

    public function testCreateReturnsS3StorageInStaging(): void
    {
        $factory = $this->createFactory('staging');
        $this->setEnv();

        $s3Client = $this->createMock(S3Client::class);
        $this->s3ClientFactory->expects($this->once())
            ->method('create')
            ->with('default', 'ap-northeast-1')
            ->willReturn($s3Client);

        $result = $factory->create();

        $this->assertInstanceOf(S3Storage::class, $result);
    }

    public function testCreateThrowsExceptionForUnsupportedEnvironment(): void
    {
        $factory = $this->createFactory('invalid');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported environment: invalid');

        $factory->create();
    }

    public function testCreateThrowsExceptionWhenAwsBucketNotSet(): void
    {
        $factory = $this->createFactory('production');
        putenv('AWS_DEFAULT_REGION=ap-northeast-1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AWS_BUCKET environment variable is not set');

        $factory->create();
    }

    public function testCreateThrowsExceptionWhenAwsRegionNotSet(): void
    {
        $factory = $this->createFactory('production');
        putenv('AWS_BUCKET=test-bucket');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AWS_DEFAULT_REGION environment variable is not set');

        $factory->create();
    }

    private function createFactory(string $environment): StorageFactory
    {
        return new StorageFactory(
            new Environment($environment),
            $this->s3ClientFactory,
            $this->logger
        );
    }

    private function setEnv(): void
    {
        putenv('AWS_BUCKET=test-bucket');
        putenv('AWS_DEFAULT_REGION=ap-northeast-1');
    }
}
