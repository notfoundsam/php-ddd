<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Cache;

use InvalidArgumentException;

class InvalidCacheArgumentException extends InvalidArgumentException
{
    public static function negativeTtl(int $ttl): self
    {
        return new self(sprintf('TTL cannot be negative, got: %d', $ttl));
    }

    public static function invalidKey(string $key, string $reason = ''): self
    {
        $message = sprintf('Invalid cache key: "%s"', $key);
        if (!empty($reason)) {
            $message .= ' - ' . $reason;
        }
        return new self($message);
    }

    public static function emptyKey(): self
    {
        return new self('Cache key cannot be empty');
    }
}
