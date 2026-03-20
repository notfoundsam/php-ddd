<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Redis\Exceptions;

use RuntimeException;

class RedisConnectionException extends RuntimeException
{
    public static function operationFailed(string $operation, string $reason = ''): self
    {
        $message = sprintf('Redis operation "%s" failed', $operation);
        if (!empty($reason)) {
            $message .= ': ' . $reason;
        }
        return new self($message);
    }
}
