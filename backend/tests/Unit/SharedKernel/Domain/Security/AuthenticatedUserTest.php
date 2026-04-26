<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Security;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserType;

class AuthenticatedUserTest extends TestCase
{
    public function testConstructionWithExplicitType(): void
    {
        $user = new AuthenticatedUser('42', 'admin@example.com', ['admin'], UserType::ADMIN);

        $this->assertSame('42', $user->getId());
        $this->assertSame('admin@example.com', $user->getEmail());
        $this->assertSame(['admin'], $user->getRoles());
        $this->assertSame(UserType::ADMIN, $user->getType());
    }

    public function testConstructionWithoutTypeDefaultsToCustomer(): void
    {
        $user = new AuthenticatedUser('1', 'user@example.com', ['user']);

        $this->assertSame(UserType::CUSTOMER, $user->getType());
        $this->assertSame([], (new AuthenticatedUser('1', 'test@example.com'))->getRoles());
    }

    public function testHasRoleReturnsTrueWhenRolePresent(): void
    {
        $user = new AuthenticatedUser('1', 'a@example.com', ['admin', 'manager']);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('manager'));
    }

    public function testHasRoleReturnsFalseWhenRoleAbsent(): void
    {
        $user = new AuthenticatedUser('1', 'a@example.com', ['admin']);

        $this->assertFalse($user->hasRole('manager'));
    }

    public function testHasRoleReturnsFalseForEmptyRoleList(): void
    {
        $user = new AuthenticatedUser('1', 'a@example.com', []);

        $this->assertFalse($user->hasRole('admin'));
    }

    public function testHasRoleIsCaseSensitive(): void
    {
        $user = new AuthenticatedUser('1', 'a@example.com', ['Admin']);

        $this->assertTrue($user->hasRole('Admin'));
        $this->assertFalse($user->hasRole('admin'));
    }
}
