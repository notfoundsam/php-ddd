<?php

declare(strict_types=1);

namespace SharedKernel\Domain\ValueObjects\Exception;

use InvalidArgumentException;

class InvalidEmailAddressException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Email address cannot be empty');
    }

    public static function invalidFormat(string $input): self
    {
        return new self("Invalid email address: '$input'");
    }
}
