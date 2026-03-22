<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Storage;

use RuntimeException;
use Throwable;

class StorageException extends RuntimeException
{
    public static function uploadFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('Failed to upload file "%s".', $path), 0, $previous);
    }

    public static function downloadFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('Failed to download file "%s".', $path), 0, $previous);
    }

    public static function deleteFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('Failed to delete file "%s".', $path), 0, $previous);
    }

    public static function fileNotFound(string $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('File "%s" not found.', $path), 0, $previous);
    }
}
