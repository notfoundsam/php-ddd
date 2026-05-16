<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Domain\ValueObjects;

use Catalog\Domain\ValueObjects\Price;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PriceTest extends TestCase
{
    public function testConstructorStoresAmountAndCurrency(): void
    {
        $price = new Price(1999, 'USD');
        $this->assertSame(1999, $price->getAmount());
        $this->assertSame('USD', $price->getCurrency());
    }

    public function testConstructorNormalizesCurrencyToUppercase(): void
    {
        $price = new Price(1000, 'usd');
        $this->assertSame('USD', $price->getCurrency());
    }

    public function testConstructorRejectsNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Price(-1, 'USD');
    }

    public function testConstructorRejectsNonIso4217Currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Price(100, 'DOLLAR');
    }

    public function testConstructorRejectsEmptyCurrency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Price(100, '');
    }

    public function testFormatForUsdUsesDollarSign(): void
    {
        $price = new Price(1999, 'USD');
        $this->assertSame('$19.99', $price->format());
    }

    public function testFormatPadsMinorUnitsToTwoDigits(): void
    {
        $price = new Price(1005, 'USD');
        $this->assertSame('$10.05', $price->format());
    }

    public function testFormatForZeroAmount(): void
    {
        $price = new Price(0, 'USD');
        $this->assertSame('$0.00', $price->format());
    }

    public function testFormatForNonUsdPrefixesCurrencyCode(): void
    {
        $price = new Price(1999, 'EUR');
        $this->assertSame('EUR 19.99', $price->format());
    }

    public function testEqualsReturnsTrueForSameAmountAndCurrency(): void
    {
        $a = new Price(1500, 'USD');
        $b = new Price(1500, 'USD');
        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentAmount(): void
    {
        $a = new Price(1500, 'USD');
        $b = new Price(1501, 'USD');
        $this->assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentCurrency(): void
    {
        $a = new Price(1500, 'USD');
        $b = new Price(1500, 'EUR');
        $this->assertFalse($a->equals($b));
    }
}
