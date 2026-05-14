<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Notification\Message;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Notification\Message\EmailSenderRole;

class EmailSenderRoleTest extends TestCase
{
    public function testNamedFactoriesReturnExpectedValues(): void
    {
        $this->assertSame('info', EmailSenderRole::info()->getValue());
        $this->assertSame('marketing', EmailSenderRole::marketing()->getValue());
        $this->assertSame('system', EmailSenderRole::system()->getValue());
    }

    public function testFromValueAcceptsValidStrings(): void
    {
        $this->assertSame('info', EmailSenderRole::fromValue('info')->getValue());
        $this->assertSame('marketing', EmailSenderRole::fromValue('marketing')->getValue());
    }

    public function testFromValueRejectsUnknownString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailSenderRole::fromValue('sales');
    }

    public function testFromValueRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailSenderRole::fromValue('');
    }

    public function testEqualsComparesValue(): void
    {
        $this->assertTrue(EmailSenderRole::system()->equals(EmailSenderRole::fromValue('system')));
        $this->assertFalse(EmailSenderRole::system()->equals(EmailSenderRole::marketing()));
    }

    public function testToStringReturnsValue(): void
    {
        $this->assertSame('marketing', (string) EmailSenderRole::marketing());
    }
}
