<?php

declare(strict_types=1);

namespace SharedKernel\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Type of a Japanese phone number, derived from its prefix.
 *
 * - mobile: 070/080/090 — SMS-reachable personal handsets
 * - landline: geographic 0X — not SMS-reachable
 * - ipPhone: 050 — VoIP, not SMS-reachable
 * - freeDial: 0120 / 0800 — toll-free, business contact
 * - premium: 0570 — Navi-dial paid routing
 * - dataTransmission: 020 / 060 — M2M / data, not voice nor SMS
 */
final class PhoneNumberType
{
    public const MOBILE = 'mobile';
    public const LANDLINE = 'landline';
    public const IP_PHONE = 'ip_phone';
    public const FREE_DIAL = 'free_dial';
    public const PREMIUM = 'premium';
    public const DATA_TRANSMISSION = 'data_transmission';

    private const VALID_VALUES = [
        self::MOBILE,
        self::LANDLINE,
        self::IP_PHONE,
        self::FREE_DIAL,
        self::PREMIUM,
        self::DATA_TRANSMISSION,
    ];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function mobile(): self
    {
        return new self(self::MOBILE);
    }

    public static function landline(): self
    {
        return new self(self::LANDLINE);
    }

    public static function ipPhone(): self
    {
        return new self(self::IP_PHONE);
    }

    public static function freeDial(): self
    {
        return new self(self::FREE_DIAL);
    }

    public static function premium(): self
    {
        return new self(self::PREMIUM);
    }

    public static function dataTransmission(): self
    {
        return new self(self::DATA_TRANSMISSION);
    }

    public static function fromValue(string $value): self
    {
        if (!in_array($value, self::VALID_VALUES, true)) {
            throw new InvalidArgumentException("Invalid phone number type: '$value'");
        }

        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(PhoneNumberType $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
