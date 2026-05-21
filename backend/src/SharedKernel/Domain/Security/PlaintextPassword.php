<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

use InvalidArgumentException;

final class PlaintextPassword
{
    private const REDACTED = '[REDACTED]';

    private string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return self::REDACTED;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => self::REDACTED];
    }
}
