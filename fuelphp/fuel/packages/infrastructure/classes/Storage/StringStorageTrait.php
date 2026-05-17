<?php

declare(strict_types=1);

namespace Infrastructure\Storage;

use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use SharedKernel\Domain\Storage\StorageException;

trait StringStorageTrait
{
    public function putString(string $path, string $content): void
    {
        $this->put($path, Utils::streamFor($content));
    }

    public function getString(string $path): string
    {
        return $this->get($path)->getContents();
    }

    public function putFile(string $path, string $filePath): void
    {
        $resource = fopen($filePath, 'r');
        if ($resource === false) {
            throw new StorageException("Failed to open file: $filePath");
        }

        try {
            $this->put($path, Utils::streamFor($resource));
        } finally {
            fclose($resource);
        }
    }

    protected function buildFullPath(string $path): string
    {
        $normalized = ltrim($path, '/');

        if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            throw new InvalidArgumentException('Path must not contain ".." segments');
        }

        return $normalized;
    }
}
