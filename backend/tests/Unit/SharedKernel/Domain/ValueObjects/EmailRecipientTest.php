<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use SharedKernel\Domain\ValueObjects\EmailRecipient;

class EmailRecipientTest extends TestCase
{
    public function testConstructionWithAddressOnly(): void
    {
        $recipient = new EmailRecipient(new EmailAddress('user@example.com'));

        $this->assertSame('user@example.com', $recipient->getEmail());
        $this->assertNull($recipient->getDisplayName());
        $this->assertFalse($recipient->hasDisplayName());
    }

    public function testConstructionWithDisplayName(): void
    {
        $recipient = new EmailRecipient(new EmailAddress('user@example.com'), 'Jane Doe');

        $this->assertSame('user@example.com', $recipient->getEmail());
        $this->assertSame('Jane Doe', $recipient->getDisplayName());
        $this->assertTrue($recipient->hasDisplayName());
    }

    public function testConstructionTrimsDisplayName(): void
    {
        $recipient = new EmailRecipient(new EmailAddress('user@example.com'), '  Jane  ');

        $this->assertSame('Jane', $recipient->getDisplayName());
    }

    public function testConstructionTreatsEmptyDisplayNameAsNull(): void
    {
        $a = new EmailRecipient(new EmailAddress('user@example.com'), '');
        $b = new EmailRecipient(new EmailAddress('user@example.com'), '   ');

        $this->assertNull($a->getDisplayName());
        $this->assertNull($b->getDisplayName());
    }

    public function testFromStringWithoutDisplayName(): void
    {
        $recipient = EmailRecipient::fromString('user@example.com');

        $this->assertSame('user@example.com', $recipient->getEmail());
        $this->assertNull($recipient->getDisplayName());
    }

    public function testFromStringWithDisplayName(): void
    {
        $recipient = EmailRecipient::fromString('user@example.com', 'Jane');

        $this->assertSame('Jane', $recipient->getDisplayName());
    }

    public function testGetAddressReturnsValueObject(): void
    {
        $address = new EmailAddress('user@example.com');
        $recipient = new EmailRecipient($address, 'Jane');

        $this->assertSame('user@example.com', $recipient->getAddress()->getEmail());
    }

    public function testWithAddressPreservesDisplayName(): void
    {
        $original = new EmailRecipient(new EmailAddress('user@example.com'), 'Jane');
        $rewritten = $original->withAddress(new EmailAddress('user--example.com@staging.test'));

        $this->assertSame('user@example.com', $original->getEmail());
        $this->assertSame('user--example.com@staging.test', $rewritten->getEmail());
        $this->assertSame('Jane', $rewritten->getDisplayName());
    }

    public function testEqualsComparesAddressAndDisplayName(): void
    {
        $a = new EmailRecipient(new EmailAddress('user@example.com'), 'Jane');
        $b = new EmailRecipient(new EmailAddress('User@Example.com'), 'Jane');
        $c = new EmailRecipient(new EmailAddress('user@example.com'), 'Bob');
        $d = new EmailRecipient(new EmailAddress('user@example.com'));

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
        $this->assertFalse($a->equals($d));
    }

    public function testToStringWithoutDisplayName(): void
    {
        $this->assertSame(
            'user@example.com',
            (string) new EmailRecipient(new EmailAddress('user@example.com'))
        );
    }

    public function testToStringWithDisplayName(): void
    {
        $this->assertSame(
            'Jane Doe <user@example.com>',
            (string) new EmailRecipient(new EmailAddress('user@example.com'), 'Jane Doe')
        );
    }

    /**
     * @dataProvider crlfDisplayNameProvider
     */
    public function testConstructionRejectsCrlfInDisplayName(string $displayName): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailRecipient(new EmailAddress('user@example.com'), $displayName);
    }

    /**
     * @return array<string, array{string}>
     */
    public function crlfDisplayNameProvider(): array
    {
        return [
            'CR' => ["Jane\rDoe"],
            'LF' => ["Jane\nDoe"],
            'CRLF' => ["Jane\r\nDoe"],
        ];
    }
}
