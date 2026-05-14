<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\ValueObjects;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\ValueObjects\PhoneNumberType;

class PhoneNumberTypeTest extends TestCase
{
    public function testNamedFactoriesReturnExpectedValues(): void
    {
        $this->assertSame('mobile', PhoneNumberType::mobile()->getValue());
        $this->assertSame('landline', PhoneNumberType::landline()->getValue());
        $this->assertSame('ip_phone', PhoneNumberType::ipPhone()->getValue());
        $this->assertSame('free_dial', PhoneNumberType::freeDial()->getValue());
        $this->assertSame('premium', PhoneNumberType::premium()->getValue());
        $this->assertSame('data_transmission', PhoneNumberType::dataTransmission()->getValue());
    }

    public function testFromValueAcceptsValidStrings(): void
    {
        $this->assertSame('mobile', PhoneNumberType::fromValue('mobile')->getValue());
        $this->assertSame('free_dial', PhoneNumberType::fromValue('free_dial')->getValue());
    }

    public function testFromValueRejectsUnknownString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhoneNumberType::fromValue('satellite');
    }

    public function testEqualsComparesValue(): void
    {
        $this->assertTrue(PhoneNumberType::mobile()->equals(PhoneNumberType::fromValue('mobile')));
        $this->assertFalse(PhoneNumberType::mobile()->equals(PhoneNumberType::landline()));
    }

    public function testToStringReturnsValue(): void
    {
        $this->assertSame('mobile', (string) PhoneNumberType::mobile());
    }
}
