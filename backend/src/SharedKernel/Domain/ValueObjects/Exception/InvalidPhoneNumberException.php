<?php

declare(strict_types=1);

namespace SharedKernel\Domain\ValueObjects\Exception;

use InvalidArgumentException;

class InvalidPhoneNumberException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Phone number cannot be empty');
    }

    public static function nonJapanese(string $input): self
    {
        return new self("Only Japan (+81) phone numbers are supported: '$input'");
    }

    public static function invalidFormat(string $input): self
    {
        return new self("Invalid phone number: '$input'");
    }

    public static function invalidLength(string $input): self
    {
        return new self("Invalid phone number length: '$input'");
    }

    public static function unknownType(string $normalized): self
    {
        return new self("Unknown phone number type for: '$normalized'");
    }
}
