<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Site\Application\Query\SearchCatalogProducts;

use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsQuery;
use PHPUnit\Framework\TestCase;

class SearchCatalogProductsQueryTest extends TestCase
{
    public function testFromHttpInputDefaultsToPageOneWhenAbsent(): void
    {
        $q = SearchCatalogProductsQuery::fromHttpInput([]);
        $this->assertSame(1, $q->getPage());
    }

    public function testFromHttpInputParsesNumericStringPage(): void
    {
        $q = SearchCatalogProductsQuery::fromHttpInput(['page' => '3']);
        $this->assertSame(3, $q->getPage());
    }

    public function testFromHttpInputAcceptsIntPage(): void
    {
        $q = SearchCatalogProductsQuery::fromHttpInput(['page' => 5]);
        $this->assertSame(5, $q->getPage());
    }

    public function testFromHttpInputCoercesNonNumericPageToOne(): void
    {
        $q = SearchCatalogProductsQuery::fromHttpInput(['page' => 'abc']);
        $this->assertSame(1, $q->getPage());
    }

    public function testFromHttpInputCoercesNegativePageToOne(): void
    {
        $q = SearchCatalogProductsQuery::fromHttpInput(['page' => '-2']);
        $this->assertSame(1, $q->getPage());
    }

    public function testFromHttpInputCoercesZeroPageToOne(): void
    {
        $q = SearchCatalogProductsQuery::fromHttpInput(['page' => 0]);
        $this->assertSame(1, $q->getPage());
    }

    public function testFromHttpInputBuildsFiltersFromSameInput(): void
    {
        $q = SearchCatalogProductsQuery::fromHttpInput([
            'category' => ['cat-1'],
            'page' => '2',
        ]);
        $this->assertSame(['cat-1'], $q->getFilters()->getCategoryIds());
        $this->assertSame(2, $q->getPage());
    }
}
