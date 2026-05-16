<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Site\Application\Query\SearchCatalogProducts;

use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsQuery;
use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsQueryHandler;
use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsResponse;
use Catalog\Application\ReadModel\ProductSearchResult;
use Catalog\Application\ReadModel\SearchFilters;
use Catalog\Application\Service\CatalogReadService;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Catalog\StubProductRepository;

class SearchCatalogProductsQueryHandlerTest extends TestCase
{
    public function testDelegatesToCatalogServiceWithPassedFiltersAndPage(): void
    {
        $repo = new StubProductRepository([], 42);
        $handler = new SearchCatalogProductsQueryHandler(new CatalogReadService($repo));

        $filters = new SearchFilters(['cat-1'], ['brand-a']);
        $response = $handler(new SearchCatalogProductsQuery($filters, 4, 20));

        $this->assertInstanceOf(SearchCatalogProductsResponse::class, $response);
        $result = $response->getResult();
        $this->assertInstanceOf(ProductSearchResult::class, $result);
        $this->assertSame(42, $result->getTotal());
        $this->assertSame(4, $result->getPage());
        $this->assertSame($filters, $repo->lastFilters);
        $this->assertSame(4, $repo->lastPage);
        $this->assertSame(20, $repo->lastPerPage);
    }
}
