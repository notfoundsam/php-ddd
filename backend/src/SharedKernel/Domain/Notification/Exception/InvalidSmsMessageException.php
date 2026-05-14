<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Exception;

use InvalidArgumentException;

class InvalidSmsMessageException extends InvalidArgumentException
{
    public static function phoneNotMobile(string $value): self
    {
        return new self("SMS recipient must be a mobile phone number: $value");
    }

    public static function emptyMessage(): self
    {
        return new self('SMS message body cannot be empty');
    }
}
