<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

final class UserType
{
    public const ADMIN = 'admin';
    public const PARTNER = 'partner';
    public const CUSTOMER = 'customer';
    public const ANONYMOUS = 'anonymous';

    /** @var array<string> */
    private static array $validTypes = [
        self::ADMIN,
        self::PARTNER,
        self::CUSTOMER,
        self::ANONYMOUS,
    ];

    private function __construct()
    {
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::$validTypes, true);
    }

    /**
     * @return array<string>
     */
    public static function getAll(): array
    {
        return self::$validTypes;
    }
}
