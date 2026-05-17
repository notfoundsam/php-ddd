<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Redis;

use SharedKernel\Domain\Redis\Exceptions\RedisConnectionException;

/**
 * Simplified Redis client interface optimized for cache operations
 * Contains only methods actually used by the cache implementation
 */
interface RedisClientInterface
{
    /**
     * Get value by key
     * @throws RedisConnectionException
     */
    public function get(string $key): ?string;

    /**
     * Set value with optional TTL
     * @param int $ttl Time to live in seconds (0 = no expiry)
     * @throws RedisConnectionException
     */
    public function set(string $key, string $value, int $ttl = 0): bool;

    /**
     * Delete key(s)
     * @param string|array $keys Single key or array of keys
     * @throws RedisConnectionException
     */
    public function del($keys): int;

    /**
     * Check if key exists
     * @throws RedisConnectionException
     */
    public function exists(string $key): bool;

    /**
     * Get multiple values by keys
     * @param array $keys
     * @return array Values in same order as keys (null for missing keys)
     * @throws RedisConnectionException
     */
    public function mget(array $keys): array;

    /**
     * Set multiple key-value pairs
     * @throws RedisConnectionException
     */
    public function mset(array $keyValues): bool;

    /**
     * Get keys matching pattern
     * @throws RedisConnectionException
     */
    public function keys(string $pattern): array;

    /**
     * Scan for keys matching pattern (production-safe alternative to keys)
     * @param string $pattern Pattern to match
     * @param int $cursor Starting cursor (0 for new scan)
     * @param int $count Number of keys to return per iteration
     * @return array ['cursor' => next_cursor, 'keys' => array_of_keys]
     * @throws RedisConnectionException
     */
    public function scan(string $pattern, int $cursor = 0, int $count = 100): array;

    /**
     * Check connection health
     * @throws RedisConnectionException
     */
    public function ping(): bool;

    /**
     * Increment the integer value of a key by one
     * @param string $key The key to increment
     * @return int The value after incrementing
     * @throws RedisConnectionException
     */
    public function incr(string $key): int;

    /**
     * Set key to hold string value with TTL if key does not exist
     * @param string $key The key to set
     * @param string $value The value to set
     * @param int $ttl Time to live in seconds (0 = no expiry)
     * @return bool True if key was set, false if key already exists
     * @throws RedisConnectionException
     */
    public function setnx(string $key, string $value, int $ttl = 0): bool;

    /**
     * Set a timeout on key
     * @param string $key The key to set expiry on
     * @param int $ttl Time to live in seconds
     * @return bool True if timeout was set, false if key does not exist
     * @throws RedisConnectionException
     */
    public function expire(string $key, int $ttl): bool;
}
