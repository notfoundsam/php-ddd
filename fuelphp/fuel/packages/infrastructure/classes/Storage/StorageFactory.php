<?php

declare(strict_types=1);

namespace Infrastructure\Storage;

use Fuel\Core\Config;
use InvalidArgumentException;
use RuntimeException;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Storage\StorageInterface;

final class StorageFactory
{
    private LoggerInterface $logger;
    private S3ClientFactory $s3ClientFactory;

    public function __construct(LoggerInterface $logger, S3ClientFactory $s3ClientFactory)
    {
        $this->logger = $logger;
        $this->s3ClientFactory = $s3ClientFactory;
    }

    public function __invoke(): StorageInterface
    {
        Config::load('storage', true);
        $driver = (string) Config::get('storage.driver', 's3');

        if ($driver === 's3') {
            return $this->createS3();
        }

        if ($driver === 'local') {
            return $this->createLocal();
        }

        throw new InvalidArgumentException(sprintf(
            'Unknown storage.driver "%s" (expected: local, s3)',
            $driver
        ));
    }

    private function createS3(): StorageInterface
    {
        $bucket = Config::get('storage.s3.bucket');
        if (empty($bucket)) {
            throw new RuntimeException('storage.s3.bucket is required when storage.driver=s3 (env AWS_BUCKET)');
        }

        $region = Config::get('storage.s3.region');
        if (empty($region)) {
            throw new RuntimeException('storage.s3.region is required when storage.driver=s3 (env AWS_DEFAULT_REGION)');
        }

        return new S3Storage(
            $this->logger,
            $this->s3ClientFactory->create('default', (string) $region),
            (string) $bucket
        );
    }

    private function createLocal(): StorageInterface
    {
        $root = Config::get('storage.local.root');
        if (empty($root)) {
            throw new RuntimeException('storage.local.root is required when storage.driver=local (env STORAGE_LOCAL_ROOT)');
        }

        return new LocalStorage($this->logger, (string) $root);
    }
}
