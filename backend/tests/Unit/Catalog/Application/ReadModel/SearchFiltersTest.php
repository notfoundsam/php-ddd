<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Application\ReadModel;

use Catalog\Application\ReadModel\SearchFilters;
use PHPUnit\Framework\TestCase;

class SearchFiltersTest extends TestCase
{
    public function testDefaultConstructorProducesEmptyFilters(): void
    {
        $f = new SearchFilters();
        $this->assertTrue($f->isEmpty());
    }

    public function testConstructorTrimsSearchTerm(): void
    {
        $f = new SearchFilters([], [], null, null, '  hello  ');
        $this->assertSame('hello', $f->getSearchTerm());
    }

    public function testConstructorTreatsBlankSearchTermAsNull(): void
    {
        $f = new SearchFilters([], [], null, null, '   ');
        $this->assertNull($f->getSearchTerm());
    }

    public function testConstructorDropsNonStringValuesFromIdArrays(): void
    {
        /** @phpstan-ignore-next-line - intentionally passing mixed values to test runtime coercion */
        $f = new SearchFilters(['cat-1', 42, null, ['nested'], 'cat-2'], ['brand-a', '']);
        $this->assertSame(['cat-1', 'cat-2'], $f->getCategoryIds());
        $this->assertSame(['brand-a'], $f->getBrandIds());
    }

    public function testToArrayRoundtripsBackToHttpFormat(): void
    {
        $f = new SearchFilters(
            ['cat-1'],
            ['brand-a', 'brand-b'],
            500,
            null,
            'widget',
        );
        $this->assertSame([
            'category' => ['cat-1'],
            'brand' => ['brand-a', 'brand-b'],
            'price_min' => 500,
            'q' => 'widget',
        ], $f->toArray());
    }
}
