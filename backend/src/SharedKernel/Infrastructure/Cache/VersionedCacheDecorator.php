<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Cache;

use SharedKernel\Domain\Cache\CacheInterface;
use SharedKernel\Domain\Cache\VersionedCacheInterface;

/**
 * Decorator that adds namespace-based versioning to any PSR-16 compliant cache
 */
class VersionedCacheDecorator implements VersionedCacheInterface
{
    private CacheInterface $cache;
    private string $contextPrefix;
    /** @var array<string, string> */
    private array $namespaceVersions;
    private string $defaultVersion;

    public function __construct(
        CacheInterface $cache,
        string $contextPrefix = '',
        array $namespaceVersions = [],
        string $defaultVersion = 'v1.0.0'
    ) {
        $this->cache = $cache;
        $this->contextPrefix = $contextPrefix;
        $this->namespaceVersions = $namespaceVersions;
        $this->defaultVersion = $defaultVersion;
    }

    public function get(string $key): ?string
    {
        $transformedKey = $this->transformKey($key);
        return $this->cache->get($transformedKey);
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        $transformedKey = $this->transformKey($key);
        return $this->cache->set($transformedKey, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $transformedKey = $this->transformKey($key);
        return $this->cache->delete($transformedKey);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function getMultiple(iterable $keys): array
    {
        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
        $transformedKeys = [];
        $keyMapping = [];

        foreach ($keyArray as $originalKey) {
            $transformedKey = $this->transformKey($originalKey);
            $transformedKeys[] = $transformedKey;
            $keyMapping[$transformedKey] = $originalKey;
        }

        $results = $this->cache->getMultiple($transformedKeys);
        $originalResults = [];

        foreach ($results as $transformedKey => $value) {
            $originalKey = $keyMapping[$transformedKey];
            $originalResults[$originalKey] = $value;
        }

        return $originalResults;
    }

    public function setMultiple(iterable $values, int $ttl = 0): bool
    {
        $valuesArray = is_array($values) ? $values : iterator_to_array($values);
        $transformedValues = [];

        foreach ($valuesArray as $originalKey => $value) {
            $transformedKey = $this->transformKey($originalKey);
            $transformedValues[$transformedKey] = $value;
        }

        return $this->cache->setMultiple($transformedValues, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
        $transformedKeys = [];

        foreach ($keyArray as $originalKey) {
            $transformedKeys[] = $this->transformKey($originalKey);
        }

        return $this->cache->deleteMultiple($transformedKeys);
    }

    public function has(string $key): bool
    {
        $transformedKey = $this->transformKey($key);
        return $this->cache->has($transformedKey);
    }

    public function getNamespaceVersion(string $namespace): string
    {
        return $this->namespaceVersions[$namespace] ?? $this->defaultVersion;
    }

    public function getNamespaceVersions(): array
    {
        return $this->namespaceVersions;
    }

    private function transformKey(string $key): string
    {
        CacheKeyTransformer::validateKey($key);

        $namespace = CacheKeyTransformer::extractNamespace($key);
        $version = $this->getNamespaceVersion($namespace);

        return CacheKeyTransformer::buildVersionedKey($key, $version, $this->contextPrefix);
    }
}
