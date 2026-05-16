<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Application\ReadModel;

use Catalog\Application\ReadModel\SearchFilters;
use PHPUnit\Framework\TestCase;

class SearchFiltersTest extends TestCase
{
    public function testFromHttpInputWithEmptyArrayProducesEmptyFilters(): void
    {
        $f = SearchFilters::fromHttpInput([]);
        $this->assertTrue($f->isEmpty());
    }

    public function testFromHttpInputCoercesCategoryAndBrandArrays(): void
    {
        $f = SearchFilters::fromHttpInput([
            'category' => ['cat-1', 'cat-2'],
            'brand' => ['brand-a'],
        ]);
        $this->assertSame(['cat-1', 'cat-2'], $f->getCategoryIds());
        $this->assertSame(['brand-a'], $f->getBrandIds());
    }

    public function testFromHttpInputDropsEmptyStringsFromArrays(): void
    {
        $f = SearchFilters::fromHttpInput([
            'category' => ['cat-1', '', 'cat-2'],
        ]);
        $this->assertSame(['cat-1', 'cat-2'], $f->getCategoryIds());
    }

    public function testFromHttpInputDropsNonStringValuesFromArrays(): void
    {
        $f = SearchFilters::fromHttpInput([
            'category' => ['cat-1', 42, null, ['nested'], 'cat-2'],
        ]);
        $this->assertSame(['cat-1', 'cat-2'], $f->getCategoryIds());
    }

    public function testFromHttpInputTreatsNonArrayCategoryAsEmpty(): void
    {
        $f = SearchFilters::fromHttpInput(['category' => 'not-an-array']);
        $this->assertSame([], $f->getCategoryIds());
    }

    public function testFromHttpInputParsesNumericStringPriceBounds(): void
    {
        $f = SearchFilters::fromHttpInput([
            'price_min' => '500',
            'price_max' => '5000',
        ]);
        $this->assertSame(500, $f->getPriceMin());
        $this->assertSame(5000, $f->getPriceMax());
    }

    public function testFromHttpInputTreatsEmptyPriceStringAsNull(): void
    {
        $f = SearchFilters::fromHttpInput([
            'price_min' => '',
            'price_max' => '',
        ]);
        $this->assertNull($f->getPriceMin());
        $this->assertNull($f->getPriceMax());
    }

    public function testFromHttpInputRejectsNonNumericPriceStrings(): void
    {
        $f = SearchFilters::fromHttpInput([
            'price_min' => 'abc',
            'price_max' => '1.5',
        ]);
        $this->assertNull($f->getPriceMin());
        $this->assertNull($f->getPriceMax());
    }

    public function testFromHttpInputAcceptsIntPriceBounds(): void
    {
        $f = SearchFilters::fromHttpInput([
            'price_min' => 100,
            'price_max' => 999,
        ]);
        $this->assertSame(100, $f->getPriceMin());
        $this->assertSame(999, $f->getPriceMax());
    }

    public function testFromHttpInputTrimsSearchTerm(): void
    {
        $f = SearchFilters::fromHttpInput(['q' => '  hello  ']);
        $this->assertSame('hello', $f->getSearchTerm());
    }

    public function testFromHttpInputTreatsBlankSearchTermAsNull(): void
    {
        $f = SearchFilters::fromHttpInput(['q' => '   ']);
        $this->assertNull($f->getSearchTerm());
    }

    public function testFromHttpInputIgnoresNonStringSearchTerm(): void
    {
        $f = SearchFilters::fromHttpInput(['q' => ['not-a-string']]);
        $this->assertNull($f->getSearchTerm());
    }

    public function testToArrayRoundtripsBackToHttpFormat(): void
    {
        $f = SearchFilters::fromHttpInput([
            'category' => ['cat-1'],
            'brand' => ['brand-a', 'brand-b'],
            'price_min' => '500',
            'q' => 'widget',
        ]);
        $this->assertSame([
            'category' => ['cat-1'],
            'brand' => ['brand-a', 'brand-b'],
            'price_min' => 500,
            'q' => 'widget',
        ], $f->toArray());
    }
}
