<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Site\Application\Query\SearchCatalogProducts;

use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsQuery;
use Catalog\Application\ReadModel\SearchFilters;
use PHPUnit\Framework\TestCase;

class SearchCatalogProductsQueryTest extends TestCase
{
    public function testPageBelowOneClampsToOne(): void
    {
        $q = new SearchCatalogProductsQuery(new SearchFilters(), -2);
        $this->assertSame(1, $q->getPage());
    }

    public function testPageZeroClampsToOne(): void
    {
        $q = new SearchCatalogProductsQuery(new SearchFilters(), 0);
        $this->assertSame(1, $q->getPage());
    }

    public function testPerPageBelowOneFallsBackToDefault(): void
    {
        $q = new SearchCatalogProductsQuery(new SearchFilters(), 1, 0);
        $this->assertSame(SearchCatalogProductsQuery::DEFAULT_PER_PAGE, $q->getPerPage());
    }
}
