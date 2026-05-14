<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\ValueObjects\Exception\InvalidPhoneNumberException;
use SharedKernel\Domain\ValueObjects\PhoneNumber;
use SharedKernel\Domain\ValueObjects\PhoneNumberType;

class PhoneNumberTest extends TestCase
{
    public function testConstructorAcceptsJapaneseMobileWithLeadingZero(): void
    {
        $phone = new PhoneNumber('090-1234-5678');

        $this->assertSame('090-1234-5678', $phone->getValue());
        $this->assertSame('+819012345678', $phone->toInternationalFormat());
        $this->assertTrue($phone->isMobile());
    }

    public function testConstructorAcceptsInternationalFormat(): void
    {
        $phone = new PhoneNumber('+819012345678');

        $this->assertSame('+819012345678', $phone->toInternationalFormat());
        $this->assertTrue($phone->isMobile());
    }

    public function testConstructorAcceptsDoubleZeroPrefixedInternational(): void
    {
        $phone = new PhoneNumber('008190-1234-5678');

        $this->assertSame('+819012345678', $phone->toInternationalFormat());
        $this->assertTrue($phone->isMobile());
    }

    public function testConstructorStripsFormattingCharacters(): void
    {
        $phone = new PhoneNumber('(080) 1234 5678');

        $this->assertSame('+818012345678', $phone->toInternationalFormat());
        $this->assertTrue($phone->isMobile());
    }

    public function testIsMobileReturnsTrueForAllMobilePrefixes(): void
    {
        $this->assertTrue((new PhoneNumber('070-1234-5678'))->isMobile());
        $this->assertTrue((new PhoneNumber('080-1234-5678'))->isMobile());
        $this->assertTrue((new PhoneNumber('090-1234-5678'))->isMobile());
    }

    public function testIsMobileReturnsFalseForLandline(): void
    {
        $phone = new PhoneNumber('03-1234-5678');

        $this->assertSame('+81312345678', $phone->toInternationalFormat());
        $this->assertFalse($phone->isMobile());
        $this->assertTrue($phone->isLandline());
    }

    public function testGetTypeDetectsMobile(): void
    {
        $this->assertSame(PhoneNumberType::MOBILE, (new PhoneNumber('090-1234-5678'))->getType()->getValue());
    }

    public function testGetTypeDetectsLandline(): void
    {
        $this->assertSame(PhoneNumberType::LANDLINE, (new PhoneNumber('03-1234-5678'))->getType()->getValue());
    }

    public function testGetTypeDetectsFreeDial0120(): void
    {
        $phone = new PhoneNumber('0120-123-456');

        $this->assertSame(PhoneNumberType::FREE_DIAL, $phone->getType()->getValue());
        $this->assertFalse($phone->isMobile());
    }

    public function testGetTypeDetectsFreeDial0800(): void
    {
        $phone = new PhoneNumber('0800-123-4567');

        $this->assertSame(PhoneNumberType::FREE_DIAL, $phone->getType()->getValue());
        $this->assertFalse($phone->isMobile());
    }

    public function testGetTypeDetectsIpPhone050(): void
    {
        $phone = new PhoneNumber('050-1234-5678');

        $this->assertSame(PhoneNumberType::IP_PHONE, $phone->getType()->getValue());
        $this->assertFalse($phone->isMobile());
    }

    public function testGetTypeDetectsPremium0570(): void
    {
        $phone = new PhoneNumber('0570-123-456');

        $this->assertSame(PhoneNumberType::PREMIUM, $phone->getType()->getValue());
    }

    public function testGetTypeDetectsDataTransmission020(): void
    {
        $phone = new PhoneNumber('020-1234-5678');

        $this->assertSame(PhoneNumberType::DATA_TRANSMISSION, $phone->getType()->getValue());
    }

    public function testConstructorRejectsEmptyInput(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);

        new PhoneNumber('');
    }

    public function testConstructorRejectsWhitespaceOnlyInput(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);

        new PhoneNumber('   ');
    }

    public function testConstructorRejectsNonJapaneseInternationalNumber(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);

        new PhoneNumber('+15551234567');
    }

    public function testConstructorRejectsTooShortNumber(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);

        new PhoneNumber('012345');
    }

    public function testConstructorRejectsTooLongNumber(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);

        new PhoneNumber('09012345678901');
    }

    public function testEqualsComparesNormalizedDigits(): void
    {
        $a = new PhoneNumber('090-1234-5678');
        $b = new PhoneNumber('+819012345678');
        $c = new PhoneNumber('080-1234-5678');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testToStringReturnsInternationalFormat(): void
    {
        $phone = new PhoneNumber('090-1234-5678');

        $this->assertSame('+819012345678', (string) $phone);
    }
}
