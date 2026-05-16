<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Site\Application\Query\ViewCatalogHomePage;

use Audience\Site\Application\Query\ViewCatalogHomePage\ViewCatalogHomePageQuery;
use Audience\Site\Application\Query\ViewCatalogHomePage\ViewCatalogHomePageQueryHandler;
use Catalog\Application\ReadModel\SearchFilters;
use Catalog\Application\Service\CatalogReadService;
use Catalog\Domain\ValueObjects\Brand;
use Catalog\Domain\ValueObjects\Category;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Catalog\StubProductRepository;

class ViewCatalogHomePageQueryHandlerTest extends TestCase
{
    public function testComposesProductsAndFacetsViaCatalogService(): void
    {
        $repo = new StubProductRepository(
            [],
            0,
            [new Category('c1', 'Electronics', 'electronics')],
            [new Brand('b1', 'Acme')]
        );
        $repo->priceMin = 1000;
        $repo->priceMax = 5000;

        $handler = new ViewCatalogHomePageQueryHandler(new CatalogReadService($repo));

        $response = $handler(new ViewCatalogHomePageQuery(new SearchFilters(), 1));

        $this->assertSame(0, $response->getProducts()->getTotal());
        $this->assertCount(1, $response->getFacets()->getCategories());
        $this->assertCount(1, $response->getFacets()->getBrands());
        $this->assertSame(1000, $response->getFacets()->getPriceMin());
        $this->assertSame(5000, $response->getFacets()->getPriceMax());
    }

    public function testForwardsFiltersAndPageToCatalogService(): void
    {
        $repo = new StubProductRepository([], 0);
        $handler = new ViewCatalogHomePageQueryHandler(new CatalogReadService($repo));

        $filters = new SearchFilters(['cat-1'], ['brand-a'], 100, 1000, 'widget');
        $handler(new ViewCatalogHomePageQuery($filters, 3));

        $this->assertSame($filters, $repo->lastFilters);
        $this->assertSame(3, $repo->lastPage);
    }
}
