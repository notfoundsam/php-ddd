<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Cache;

use RuntimeException;

class CacheException extends RuntimeException
{
    public static function connectionFailed(string $reason = ''): self
    {
        $message = 'Cache backend connection failed';
        if (!empty($reason)) {
            $message .= ': ' . $reason;
        }
        return new self($message);
    }
}
