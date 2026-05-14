<?php

declare(strict_types=1);

namespace SharedKernel\Domain\ValueObjects;

use SharedKernel\Domain\ValueObjects\Exception\InvalidPhoneNumberException;

final class PhoneNumber
{
    private string $value;
    private string $normalized;
    private PhoneNumberType $type;

    public function __construct(string $value)
    {
        $original = trim($value);

        if ($original === '') {
            throw InvalidPhoneNumberException::empty();
        }

        $this->value = $original;
        $this->normalized = self::normalize($original);
        $this->type = self::detectType($this->normalized, $original);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getType(): PhoneNumberType
    {
        return $this->type;
    }

    public function isMobile(): bool
    {
        return $this->type->getValue() === PhoneNumberType::MOBILE;
    }

    public function isLandline(): bool
    {
        return $this->type->getValue() === PhoneNumberType::LANDLINE;
    }

    public function toInternationalFormat(): string
    {
        return '+81' . substr($this->normalized, 1);
    }

    public function equals(PhoneNumber $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    public function __toString(): string
    {
        return $this->toInternationalFormat();
    }

    private static function normalize(string $input): string
    {
        $digits = preg_replace('/[^\d+]/', '', $input);

        if ($digits === '' || $digits === null) {
            throw InvalidPhoneNumberException::invalidFormat($input);
        }

        if (strpos($digits, '+81') === 0) {
            $national = substr($digits, 3);
        } elseif (strpos($digits, '0081') === 0) {
            $national = substr($digits, 4);
        } elseif (strpos($digits, '+') === 0) {
            throw InvalidPhoneNumberException::nonJapanese($input);
        } elseif (strpos($digits, '0') === 0) {
            $national = substr($digits, 1);
        } else {
            $national = $digits;
        }

        if ($national === '' || ctype_digit($national) === false) {
            throw InvalidPhoneNumberException::invalidFormat($input);
        }

        if (strlen($national) < 9 || strlen($national) > 10) {
            throw InvalidPhoneNumberException::invalidLength($input);
        }

        return '0' . $national;
    }

    private static function detectType(string $normalized, string $original): PhoneNumberType
    {
        // Free-dial 0120/0800 — checked before mobile to avoid 080 prefix collision
        if (preg_match('/^0120\d{6}$/', $normalized)) {
            return PhoneNumberType::freeDial();
        }

        if (preg_match('/^0800\d{7}$/', $normalized)) {
            return PhoneNumberType::freeDial();
        }

        if (preg_match('/^(090|080|070)\d{8}$/', $normalized)) {
            return PhoneNumberType::mobile();
        }

        if (preg_match('/^0570\d{6}$/', $normalized)) {
            return PhoneNumberType::premium();
        }

        if (preg_match('/^050\d{8}$/', $normalized)) {
            return PhoneNumberType::ipPhone();
        }

        if (preg_match('/^(020|060)\d{8}$/', $normalized)) {
            return PhoneNumberType::dataTransmission();
        }

        // Geographic landline: leading 0, then any non-overlapping prefix
        if (preg_match('/^0(?!120|800|570|50|20|60|90|80|70)[1-9]\d{8,9}$/', $normalized)) {
            return PhoneNumberType::landline();
        }

        throw InvalidPhoneNumberException::unknownType($original);
    }
}
