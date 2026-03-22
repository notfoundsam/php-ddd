<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Storage;

final class CdnUrlResolver
{
    /** @var array<string, string> */
    private array $mappings;

    /**
     * @param array<string, string> $mappings prefix => CDN base URL
     */
    public function __construct(array $mappings)
    {
        $this->mappings = $mappings;
    }

    public function resolve(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        foreach ($this->mappings as $prefix => $cdnDomain) {
            if (empty($cdnDomain)) {
                continue;
            }

            if (strpos($path, $prefix) === 0) {
                $relativePath = substr($path, strlen($prefix));

                if (empty($relativePath)) {
                    return '';
                }

                return rtrim($cdnDomain, '/') . '/' . ltrim($relativePath, '/');
            }
        }

        return '';
    }
}
