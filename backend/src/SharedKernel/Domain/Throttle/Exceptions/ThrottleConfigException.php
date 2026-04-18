<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle\Exceptions;

use InvalidArgumentException;

class ThrottleConfigException extends InvalidArgumentException
{
    public static function missingRequiredKeys(): self
    {
        return new self('Throttle configuration must include warning_limit, block_limit, and window');
    }

    public static function warningLimitTooHigh(): self
    {
        return new self('warning_limit must be less than block_limit');
    }

    public static function invalidValue(string $key, string $reason): self
    {
        return new self("Invalid throttle configuration value for '$key': $reason");
    }
}
