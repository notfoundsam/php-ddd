<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Cache;

use SharedKernel\Domain\Cache\CacheInterface;
use SharedKernel\Domain\Cache\InvalidCacheArgumentException;
use SharedKernel\Domain\Redis\Exceptions\RedisConnectionException;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Domain\Cache\CacheException;

class RedisCache implements CacheInterface
{
    private const REDIS_KEY_PREFIX = 'cache';

    private RedisClientInterface $redis;

    public function __construct(RedisClientInterface $redis)
    {
        $this->redis = $redis;
    }

    public function get(string $key): ?string
    {
        CacheKeyTransformer::validateKey($key);

        try {
            return $this->redis->get(self::REDIS_KEY_PREFIX . ':' . $key);
        } catch (RedisConnectionException $e) {
            throw CacheException::connectionFailed($e->getMessage());
        }
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        CacheKeyTransformer::validateKey($key);
        $this->validateTtl($ttl);

        try {
            return $this->redis->set(self::REDIS_KEY_PREFIX . ':' . $key, $value, $ttl);
        } catch (RedisConnectionException $e) {
            throw CacheException::connectionFailed($e->getMessage());
        }
    }

    public function delete(string $key): bool
    {
        CacheKeyTransformer::validateKey($key);

        try {
            $this->redis->del(self::REDIS_KEY_PREFIX . ':' . $key);
            return true;
        } catch (RedisConnectionException $e) {
            throw CacheException::connectionFailed($e->getMessage());
        }
    }

    public function has(string $key): bool
    {
        CacheKeyTransformer::validateKey($key);

        try {
            return $this->redis->exists(self::REDIS_KEY_PREFIX . ':' . $key);
        } catch (RedisConnectionException $e) {
            throw CacheException::connectionFailed($e->getMessage());
        }
    }

    public function getMultiple(iterable $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
        CacheKeyTransformer::validateKeysArray($keyArray);

        $prefixedKeys = array_map(function ($key) {
            return self::REDIS_KEY_PREFIX . ':' . $key;
        }, $keyArray);

        try {
            $values = $this->redis->mget($prefixedKeys);
        } catch (RedisConnectionException $e) {
            throw CacheException::connectionFailed($e->getMessage());
        }

        $result = [];
        for ($i = 0; $i < count($keyArray); $i++) {
            $result[$keyArray[$i]] = $values[$i];
        }

        return $result;
    }

    public function setMultiple(iterable $values, int $ttl = 0): bool
    {
        if (empty($values)) {
            return true;
        }

        $valuesArray = is_array($values) ? $values : iterator_to_array($values);
        $this->validateTtl($ttl);
        CacheKeyTransformer::validateKeyValuePairsArray($valuesArray);

        try {
            if ($ttl === 0) {
                // Set without TTL using mset
                $prefixedValues = [];
                foreach ($valuesArray as $key => $value) {
                    $prefixedValues[self::REDIS_KEY_PREFIX . ':' . $key] = $value;
                }
                return $this->redis->mset($prefixedValues);
            }

            // Set each key individually with TTL
            foreach ($valuesArray as $key => $value) {
                $this->redis->set(self::REDIS_KEY_PREFIX . ':' . $key, $value, $ttl);
            }
            return true;
        } catch (RedisConnectionException $e) {
            throw CacheException::connectionFailed($e->getMessage());
        }
    }

    public function deleteMultiple(iterable $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
        CacheKeyTransformer::validateKeysArray($keyArray);

        $prefixedKeys = array_map(function ($key) {
            return self::REDIS_KEY_PREFIX . ':' . $key;
        }, $keyArray);

        try {
            $this->redis->del($prefixedKeys);
            return true;
        } catch (RedisConnectionException $e) {
            throw CacheException::connectionFailed($e->getMessage());
        }
    }

    public function clear(): bool
    {
        try {
            $cursor = 0;
            do {
                $result = $this->redis->scan(self::REDIS_KEY_PREFIX . ':*', $cursor);
                $cursor = $result['cursor'];

                if (empty($result['keys'])) {
                    continue;
                }

                $this->redis->del($result['keys']);
            } while ($cursor !== 0);

            return true;
        } catch (RedisConnectionException $e) {
            throw CacheException::connectionFailed($e->getMessage());
        }
    }

    private function validateTtl(int $ttl): void
    {
        if ($ttl < 0) {
            throw InvalidCacheArgumentException::negativeTtl($ttl);
        }
    }
}
