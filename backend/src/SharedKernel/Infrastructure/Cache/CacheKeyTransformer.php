<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Cache;

use SharedKernel\Domain\Cache\InvalidCacheArgumentException;

/**
 * Utility class for cache key transformation and namespace extraction
 */
class CacheKeyTransformer
{
    private const MAX_KEY_LENGTH = 512;

    /**
     * Extract namespace from a cache key (part before the first colon)
     */
    public static function extractNamespace(string $key): string
    {
        $colonPos = strpos($key, ':');

        if ($colonPos === false) {
            return 'default';
        }

        return substr($key, 0, $colonPos);
    }

    /**
     * Build versioned key with context prefix and version
     */
    public static function buildVersionedKey(
        string $originalKey,
        string $version,
        string $contextPrefix = ''
    ): string {
        $parts = [];

        if (!empty($contextPrefix)) {
            $parts[] = $contextPrefix;
        }

        $parts[] = $version;
        $parts[] = $originalKey;

        return implode(':', $parts);
    }

    /**
     * Validate a cache key format and constraints
     */
    public static function validateKey(string $key): void
    {
        if (empty($key)) {
            throw InvalidCacheArgumentException::emptyKey();
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw InvalidCacheArgumentException::invalidKey(
                $key,
                sprintf('key too long (max %d characters)', self::MAX_KEY_LENGTH)
            );
        }

        // Check for invalid characters that might cause issues
        if (preg_match('/[\r\n\t\f\v\0]/', $key)) {
            throw InvalidCacheArgumentException::invalidKey(
                $key,
                'key contains invalid characters'
            );
        }
    }

    /**
     * Validate an array of cache keys
     */
    public static function validateKeysArray(array $keys): void
    {
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw InvalidCacheArgumentException::invalidKey((string) $key, 'key must be string');
            }
            self::validateKey($key);
        }
    }

    /**
     * Validate keys in a key-value pairs array
     */
    public static function validateKeyValuePairsArray(array $values): void
    {
        foreach (array_keys($values) as $key) {
            if (!is_string($key)) {
                throw InvalidCacheArgumentException::invalidKey((string) $key, 'key must be string');
            }
            self::validateKey($key);
        }
    }
}
