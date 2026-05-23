<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

use InvalidArgumentException;

final class PlaintextPassword
{
    private const REDACTED = '[REDACTED]';

    /**
     * Bcrypt silently truncates input beyond 72 bytes. Rejecting at the VO boundary
     * stops a user from registering "passwordA" (where the 73rd byte is "A") and then
     * logging in with "passwordB" — both hash to the same bcrypt because bytes past
     * the 72nd are ignored.
     */
    private const MAX_BYTES = 72;

    private string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        if (strlen($value) > self::MAX_BYTES) {
            throw new InvalidArgumentException(
                'Password cannot exceed ' . self::MAX_BYTES . ' bytes (bcrypt truncates beyond this length).'
            );
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
