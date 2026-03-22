<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Storage;

use InvalidArgumentException;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Storage\StorageInterface;

final class StorageFactory
{
    private Environment $environment;
    private S3ClientFactory $s3ClientFactory;
    private LoggerInterface $logger;

    public function __construct(
        Environment $environment,
        S3ClientFactory $s3ClientFactory,
        LoggerInterface $logger
    ) {
        $this->environment = $environment;
        $this->s3ClientFactory = $s3ClientFactory;
        $this->logger = $logger;
    }

    public function create(): StorageInterface
    {
        if ($this->environment->isCloudLike()) {
            return $this->createS3();
        }

        if ($this->environment->isDevelopment()) {
            return $this->createLocalStorage();
        }

        throw new InvalidArgumentException("Unsupported environment: {$this->environment->getValue()}");
    }

    private function createS3(): StorageInterface
    {
        $bucket = getenv('AWS_BUCKET');
        if ($bucket === false || $bucket === '') {
            throw new InvalidArgumentException('AWS_BUCKET environment variable is not set');
        }

        $region = getenv('AWS_DEFAULT_REGION');
        if ($region === false || $region === '') {
            throw new InvalidArgumentException('AWS_DEFAULT_REGION environment variable is not set');
        }

        return new S3Storage(
            $this->logger,
            $this->s3ClientFactory->create('default', $region),
            $bucket
        );
    }

    private function createLocalStorage(): StorageInterface
    {
        return new LocalStorage($this->logger);
    }
}
