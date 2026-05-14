<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Message;

use InvalidArgumentException;

final class EmailSenderRole
{
    public const INFO = 'info';
    public const MARKETING = 'marketing';
    public const SYSTEM = 'system';

    private const VALID_VALUES = [
        self::INFO,
        self::MARKETING,
        self::SYSTEM,
    ];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function info(): self
    {
        return new self(self::INFO);
    }

    public static function marketing(): self
    {
        return new self(self::MARKETING);
    }

    public static function system(): self
    {
        return new self(self::SYSTEM);
    }

    public static function fromValue(string $value): self
    {
        if (!in_array($value, self::VALID_VALUES, true)) {
            throw new InvalidArgumentException("Invalid email sender role: '$value'");
        }

        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(EmailSenderRole $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
