<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use SharedKernel\Domain\ValueObjects\Exception\InvalidEmailAddressException;

class EmailAddressTest extends TestCase
{
    public function testConstructorAcceptsValidEmail(): void
    {
        $email = new EmailAddress('user@example.com');

        $this->assertSame('user@example.com', $email->getEmail());
    }

    public function testConstructorTrimsAndLowercases(): void
    {
        $email = new EmailAddress('  USER@Example.COM  ');

        $this->assertSame('user@example.com', $email->getEmail());
    }

    public function testConstructorRejectsEmpty(): void
    {
        $this->expectException(InvalidEmailAddressException::class);

        new EmailAddress('');
    }

    public function testConstructorRejectsMalformedEmail(): void
    {
        $this->expectException(InvalidEmailAddressException::class);

        new EmailAddress('not-an-email');
    }

    public function testConstructorRejectsEmailWithoutDomain(): void
    {
        $this->expectException(InvalidEmailAddressException::class);

        new EmailAddress('user@');
    }

    public function testFromStringReturnsInstance(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        $this->assertSame('user@example.com', $email->getEmail());
    }

    public function testEqualsCompareNormalizedEmail(): void
    {
        $a = new EmailAddress('User@Example.com');
        $b = new EmailAddress('user@example.com');
        $c = new EmailAddress('other@example.com');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testToStringReturnsEmail(): void
    {
        $this->assertSame('user@example.com', (string) new EmailAddress('user@example.com'));
    }
}
