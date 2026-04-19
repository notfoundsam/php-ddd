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
    }

    public function testConstructionWithPartnerType(): void
    {
        $user = new AuthenticatedUser('10', 'partner@example.com', ['partner'], UserType::PARTNER);

        $this->assertSame(UserType::PARTNER, $user->getType());
    }

    public function testConstructionWithCustomerType(): void
    {
        $user = new AuthenticatedUser('20', 'customer@example.com', [], UserType::CUSTOMER);

        $this->assertSame(UserType::CUSTOMER, $user->getType());
    }

    public function testConstructionWithMinimalArguments(): void
    {
        $user = new AuthenticatedUser('1', 'test@example.com');

        $this->assertSame('1', $user->getId());
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame([], $user->getRoles());
        $this->assertSame(UserType::CUSTOMER, $user->getType());
    }
}
