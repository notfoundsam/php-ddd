<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Security;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\UserType;

class UserTypeTest extends TestCase
{
    public function testIsValidReturnsTrueForAllDefinedTypes(): void
    {
        $this->assertTrue(UserType::isValid(UserType::ADMIN));
        $this->assertTrue(UserType::isValid(UserType::PARTNER));
        $this->assertTrue(UserType::isValid(UserType::CUSTOMER));
        $this->assertTrue(UserType::isValid(UserType::SYSTEM));
        $this->assertTrue(UserType::isValid(UserType::ANONYMOUS));
    }

    public function testIsValidReturnsFalseForUnknownType(): void
    {
        $this->assertFalse(UserType::isValid('superadmin'));
        $this->assertFalse(UserType::isValid(''));
        $this->assertFalse(UserType::isValid('ADMIN'));
    }

    public function testGetAllReturnsAllDefinedTypes(): void
    {
        $all = UserType::getAll();

        $this->assertCount(5, $all);
        $this->assertContains(UserType::ADMIN, $all);
        $this->assertContains(UserType::PARTNER, $all);
        $this->assertContains(UserType::CUSTOMER, $all);
        $this->assertContains(UserType::SYSTEM, $all);
        $this->assertContains(UserType::ANONYMOUS, $all);
    }

    public function testConstantValues(): void
    {
        $this->assertSame('admin', UserType::ADMIN);
        $this->assertSame('partner', UserType::PARTNER);
        $this->assertSame('customer', UserType::CUSTOMER);
        $this->assertSame('system', UserType::SYSTEM);
        $this->assertSame('anonymous', UserType::ANONYMOUS);
    }
}
