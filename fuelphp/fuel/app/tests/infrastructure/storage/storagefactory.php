<?php

namespace Fuel\Core;

use Aws\S3\S3Client;
use Infrastructure\Storage\LocalStorage;
use Infrastructure\Storage\S3ClientFactory;
use Infrastructure\Storage\S3Storage;
use Infrastructure\Storage\StorageFactory;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use SharedKernel\Domain\Logger\LoggerInterface;

/**
 * @group App
 * @group Storage
 */
class Test_StorageFactory extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Config::load('storage', 'storage', true, true);
    }

    public function tearDown(): void
    {
        Config::load('storage', 'storage', true, true);
        parent::tearDown();
    }

    public function test_builds_local_storage_with_configured_root()
    {
        Config::set('storage.driver', 'local');
        Config::set('storage.local.root', '/tmp/php-ddd-test-storage');

        $storage = (new StorageFactory($this->logger(), new S3ClientFactory()))();

        $this->assertInstanceOf(LocalStorage::class, $storage);
        $this->assertSame('/tmp/php-ddd-test-storage/', $this->extractProperty($storage, 'root'));
    }

    public function test_builds_s3_storage_with_configured_bucket_and_region()
    {
        Config::set('storage.driver', 's3');
        Config::set('storage.s3.bucket', 'my-bucket');
        Config::set('storage.s3.region', 'ap-northeast-1');

        $storage = (new StorageFactory($this->logger(), $this->fakeS3ClientFactory()))();

        $this->assertInstanceOf(S3Storage::class, $storage);
        $this->assertSame('my-bucket', $this->extractProperty($storage, 'bucket'));
    }

    public function test_local_throws_when_root_is_empty()
    {
        Config::set('storage.driver', 'local');
        Config::set('storage.local.root', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('storage.local.root is required when storage.driver=local (env STORAGE_LOCAL_ROOT)');

        (new StorageFactory($this->logger(), new S3ClientFactory()))();
    }

    public function test_s3_throws_when_bucket_is_empty()
    {
        Config::set('storage.driver', 's3');
        Config::set('storage.s3.bucket', null);
        Config::set('storage.s3.region', 'ap-northeast-1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('storage.s3.bucket is required when storage.driver=s3 (env AWS_BUCKET)');

        (new StorageFactory($this->logger(), $this->fakeS3ClientFactory()))();
    }

    public function test_s3_throws_when_region_is_empty()
    {
        Config::set('storage.driver', 's3');
        Config::set('storage.s3.bucket', 'my-bucket');
        Config::set('storage.s3.region', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('storage.s3.region is required when storage.driver=s3 (env AWS_DEFAULT_REGION)');

        (new StorageFactory($this->logger(), $this->fakeS3ClientFactory()))();
    }

    public function test_throws_on_unknown_driver()
    {
        Config::set('storage.driver', 'ftp');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown storage.driver "ftp" (expected: local, s3)');

        (new StorageFactory($this->logger(), new S3ClientFactory()))();
    }

    private function logger(): LoggerInterface
    {
        return $this->createMock(LoggerInterface::class);
    }

    private function fakeS3ClientFactory(): S3ClientFactory
    {
        $factory = $this->createMock(S3ClientFactory::class);
        $factory->method('create')->willReturn($this->createMock(S3Client::class));
        return $factory;
    }

    /**
     * @param object $object
     * @return mixed
     */
    private function extractProperty($object, string $property)
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
}
