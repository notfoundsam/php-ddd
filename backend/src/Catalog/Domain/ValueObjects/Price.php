<?php

declare(strict_types=1);

namespace Catalog\Domain\ValueObjects;

use InvalidArgumentException;

final class Price
{
    private int $amount;
    private string $currency;

    public function __construct(int $amount, string $currency)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Price amount cannot be negative.');
        }
        $currency = strtoupper(trim($currency));
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO 4217 code.');
        }
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function format(): string
    {
        $major = intdiv($this->amount, 100);
        $minor = $this->amount % 100;
        $symbol = $this->currency === 'USD' ? '$' : $this->currency . ' ';
        return sprintf('%s%d.%02d', $symbol, $major, $minor);
    }

    public function equals(Price $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
