<?php

declare(strict_types=1);

namespace Infrastructure\Storage;

use Exception;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Storage\StorageException;
use SharedKernel\Domain\Storage\StorageInterface;

final class LocalStorage implements StorageInterface
{
    use StringStorageTrait;

    private LoggerInterface $logger;
    private string $root;

    public function __construct(LoggerInterface $logger, string $root)
    {
        $this->logger = $logger;
        $this->root = rtrim($root, '/') . '/';
    }

    public function put(string $path, StreamInterface $stream): void
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        $fullPath = $this->buildFullPath($path);
        $content = $stream->getContents();

        try {
            $this->saveFile($fullPath, $content);
        } catch (Exception $e) {
            $this->logger->error('Local storage upload failed', [
                'path' => $fullPath,
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
        $storagePath = $this->root . $fullPath;

        if (!file_exists($storagePath)) {
            throw StorageException::fileNotFound($path);
        }

        try {
            $content = file_get_contents($storagePath);
            if ($content === false) {
                throw new Exception("Failed to read file: $storagePath");
            }
            return Utils::streamFor($content);
        } catch (Exception $e) {
            $this->logger->error('Local storage download failed', [
                'path' => $fullPath,
                'storage_path' => $storagePath,
                'exception' => $e,
            ]);
            throw StorageException::downloadFailed($path, $e);
        }
    }

    public function delete(string $path): void
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        $fullPath = $this->buildFullPath($path);
        $storagePath = $this->root . $fullPath;

        if (!file_exists($storagePath)) {
            throw StorageException::fileNotFound($path);
        }

        try {
            if (!unlink($storagePath)) {
                throw new Exception("Failed to delete file: $storagePath");
            }
        } catch (Exception $e) {
            $this->logger->error('Local storage delete failed', [
                'path' => $fullPath,
                'storage_path' => $storagePath,
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

        return file_exists($this->root . $fullPath);
    }

    public function getSize(string $path): int
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        $fullPath = $this->buildFullPath($path);
        $storagePath = $this->root . $fullPath;

        if (!file_exists($storagePath)) {
            throw StorageException::fileNotFound($path);
        }

        return filesize($storagePath) ?: 0;
    }

    public function copy(string $from, string $to): void
    {
        if (empty($from) || empty($to)) {
            throw new InvalidArgumentException('Source and destination paths cannot be empty');
        }

        $fullFromPath = $this->buildFullPath($from);
        $fullToPath = $this->buildFullPath($to);
        $storageFromPath = $this->root . $fullFromPath;

        if (!file_exists($storageFromPath)) {
            throw StorageException::fileNotFound($from);
        }

        try {
            $content = file_get_contents($storageFromPath);
            if ($content === false) {
                throw new Exception("Failed to read file: $storageFromPath");
            }
            $this->saveFile($fullToPath, $content);
        } catch (Exception $e) {
            $this->logger->error('Local storage copy failed', [
                'from' => $fullFromPath,
                'to' => $fullToPath,
                'exception' => $e,
            ]);
            throw StorageException::uploadFailed($to, $e);
        }
    }

    private function saveFile(string $path, string $content): void
    {
        $storagePath = $this->root . $path;
        $directory = dirname($storagePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new Exception("Failed to create directory: $directory");
            }
        }

        if (file_put_contents($storagePath, $content) === false) {
            throw new Exception("Failed to write file: $storagePath");
        }
    }
}
