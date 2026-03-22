<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Storage;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Storage\StorageException;
use SharedKernel\Domain\Storage\StorageInterface;

final class S3Storage implements StorageInterface
{
    use StringStorageTrait;

    private LoggerInterface $logger;
    private S3Client $s3Client;
    private string $bucket;

    public function __construct(
        LoggerInterface $logger,
        S3Client $s3Client,
        string $bucket
    ) {
        $this->logger = $logger;
        $this->s3Client = $s3Client;
        $this->bucket = $bucket;
    }

    public function put(string $path, StreamInterface $stream): void
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        $fullPath = $this->buildFullPath($path);

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $fullPath,
                'Body' => $stream,
            ]);
        } catch (Exception $e) {
            $this->logger->error('S3 upload failed', [
                'bucket' => $this->bucket,
                'key' => $fullPath,
                'exception' => $e,
            ]);
            throw StorageException::uploadFailed($path, $e);
        }
    }

    public function get(string $path): StreamInterface
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        $fullPath = $this->buildFullPath($path);

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $fullPath,
            ]);
        } catch (Exception $e) {
            $this->logger->error('S3 download failed', [
                'bucket' => $this->bucket,
                'key' => $fullPath,
                'exception' => $e,
            ]);
            throw StorageException::downloadFailed($path, $e);
        }

        return $result['Body'];
    }

    public function delete(string $path): void
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        if (!$this->exists($path)) {
            throw StorageException::fileNotFound($path);
        }

        $fullPath = $this->buildFullPath($path);

        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $fullPath,
            ]);
        } catch (Exception $e) {
            $this->logger->error('S3 delete failed', [
                'bucket' => $this->bucket,
                'key' => $fullPath,
                'exception' => $e,
            ]);
            throw StorageException::deleteFailed($path, $e);
        }
    }

    public function exists(string $path): bool
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        $fullPath = $this->buildFullPath($path);

        try {
            $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $fullPath,
            ]);
            return true;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    public function getSize(string $path): int
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        $fullPath = $this->buildFullPath($path);

        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $fullPath,
            ]);
            return (int) $result['ContentLength'];
        } catch (Exception $e) {
            $this->logger->error('S3 getSize failed', [
                'bucket' => $this->bucket,
                'key' => $fullPath,
                'exception' => $e,
            ]);
            throw StorageException::fileNotFound($path, $e);
        }
    }

    public function copy(string $from, string $to): void
    {
        if (empty($from) || empty($to)) {
            throw new InvalidArgumentException('Source and destination paths cannot be empty');
        }

        $fullFromPath = $this->buildFullPath($from);
        $fullToPath = $this->buildFullPath($to);

        try {
            $this->s3Client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $fullToPath,
                'CopySource' => $this->bucket . '/'
                    . implode('/', array_map('rawurlencode', explode('/', $fullFromPath))),
            ]);
        } catch (Exception $e) {
            $this->logger->error('S3 copy failed', [
                'bucket' => $this->bucket,
                'from' => $fullFromPath,
                'to' => $fullToPath,
                'exception' => $e,
            ]);
            throw StorageException::uploadFailed($to, $e);
        }
    }
}
