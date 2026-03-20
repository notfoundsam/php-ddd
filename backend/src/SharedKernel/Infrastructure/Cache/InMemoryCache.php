<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Cache;

use SharedKernel\Domain\Cache\CacheInterface;

class InMemoryCache implements CacheInterface
{
    /** @var array<string, string> */
    private array $cache = [];

    /** @var array<string, int> */
    private array $expiry = [];

    public function get(string $key): ?string
    {
        CacheKeyTransformer::validateKey($key);
        if (!isset($this->cache[$key])) {
            return null;
        }

        if (isset($this->expiry[$key]) && time() > $this->expiry[$key]) {
            $this->delete($key);
            return null;
        }

        return $this->cache[$key];
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        CacheKeyTransformer::validateKey($key);
        $this->cache[$key] = $value;

        if ($ttl > 0) {
            $this->expiry[$key] = time() + $ttl;
        } else {
            unset($this->expiry[$key]);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        CacheKeyTransformer::validateKey($key);
        unset($this->cache[$key]);
        unset($this->expiry[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        CacheKeyTransformer::validateKey($key);
        if (!isset($this->cache[$key])) {
            return false;
        }

        if (isset($this->expiry[$key]) && time() > $this->expiry[$key]) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function getMultiple(iterable $keys): array
    {
        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
        CacheKeyTransformer::validateKeysArray($keyArray);
        $result = [];
        foreach ($keyArray as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function setMultiple(iterable $values, int $ttl = 0): bool
    {
        $valuesArray = is_array($values) ? $values : iterator_to_array($values);
        CacheKeyTransformer::validateKeyValuePairsArray($valuesArray);
        foreach ($valuesArray as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
        CacheKeyTransformer::validateKeysArray($keyArray);
        foreach ($keyArray as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->expiry = [];
        return true;
    }
}
