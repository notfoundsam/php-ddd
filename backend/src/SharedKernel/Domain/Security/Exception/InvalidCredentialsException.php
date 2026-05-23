<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\Exception;

use DomainException;

final class InvalidCredentialsException extends DomainException
{
    public static function create(): self
    {
        return new self('Invalid credentials.');
    }
}
