<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Cache;

/**
 * Cache interface for string-based cache operations
 */
interface CacheInterface
{
    public function get(string $key): ?string;

    /**
     * @param int $ttl Time to live in seconds (0 = no expiry)
     */
    public function set(string $key, string $value, int $ttl = 0): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function getMultiple(iterable $keys): array;

    /**
     * @param int $ttl Time to live in seconds (0 = no expiry)
     */
    public function setMultiple(iterable $values, int $ttl = 0): bool;

    public function deleteMultiple(iterable $keys): bool;

    public function has(string $key): bool;
}
