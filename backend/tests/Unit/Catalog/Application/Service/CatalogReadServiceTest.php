<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Application\Service;

use Catalog\Application\ReadModel\SearchFilters;
use Catalog\Application\Service\CatalogReadService;
use Catalog\Domain\Product;
use Catalog\Domain\ValueObjects\Brand;
use Catalog\Domain\ValueObjects\Category;
use Catalog\Domain\ValueObjects\Price;
use Catalog\Domain\ValueObjects\ProductId;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Catalog\StubProductRepository;

class CatalogReadServiceTest extends TestCase
{
    public function testSearchProductsReturnsItemsWithJoinedBrandAndCategoryNames(): void
    {
        $repo = new StubProductRepository(
            [$this->makeProduct('p1', 'Widget', 'b1', 'c1', 1999)],
            1,
            [new Category('c1', 'Electronics', 'electronics')],
            [new Brand('b1', 'Acme')]
        );
        $service = new CatalogReadService($repo);

        $response = $service->searchProducts(new SearchFilters(), 1, 12);

        $this->assertCount(1, $response->getItems());
        $item = $response->getItems()[0];
        $this->assertSame('p1', $item->getId());
        $this->assertSame('Widget', $item->getName());
        $this->assertSame('Acme', $item->getBrandName());
        $this->assertSame('Electronics', $item->getCategoryName());
        $this->assertSame('$19.99', $item->getPriceFormatted());
    }

    public function testSearchProductsPassesFiltersAndPaginationToRepository(): void
    {
        $repo = new StubProductRepository([], 0);
        $service = new CatalogReadService($repo);

        $filters = new SearchFilters(['c1'], ['b1'], 500, 5000, 'widget');
        $service->searchProducts($filters, 3, 20);

        $this->assertSame($filters, $repo->lastFilters);
        $this->assertSame(3, $repo->lastPage);
        $this->assertSame(20, $repo->lastPerPage);
    }

    public function testSearchProductsComputesTotalPagesFromTotalAndPerPage(): void
    {
        $repo = new StubProductRepository([], 25);
        $service = new CatalogReadService($repo);

        $response = $service->searchProducts(new SearchFilters(), 1, 10);

        $this->assertSame(25, $response->getTotal());
        $this->assertSame(3, $response->getTotalPages());
    }

    public function testSearchProductsEmptyResultReturnsZeroTotalPages(): void
    {
        $repo = new StubProductRepository([], 0);
        $service = new CatalogReadService($repo);

        $response = $service->searchProducts(new SearchFilters(), 1, 12);

        $this->assertSame([], $response->getItems());
        $this->assertSame(0, $response->getTotal());
        $this->assertSame(0, $response->getTotalPages());
    }

    public function testSearchProductsCoercesZeroPageToOne(): void
    {
        $repo = new StubProductRepository([], 5);
        $service = new CatalogReadService($repo);

        $response = $service->searchProducts(new SearchFilters(), 0, 12);

        $this->assertSame(1, $response->getPage());
    }

    public function testSearchProductsCoercesZeroPerPageToFallback(): void
    {
        $repo = new StubProductRepository([], 5);
        $service = new CatalogReadService($repo);

        $response = $service->searchProducts(new SearchFilters(), 1, 0);

        $this->assertSame(12, $response->getPerPage());
    }

    public function testSearchProductsCapsExtremePageBeforeReachingRepository(): void
    {
        $repo = new StubProductRepository([], 0);
        $service = new CatalogReadService($repo);

        $service->searchProducts(new SearchFilters(), 99999999, 12);

        $this->assertSame(10000, $repo->lastPage);
    }

    public function testSearchProductsItemsForUnknownBrandFallBackToEmptyName(): void
    {
        $repo = new StubProductRepository(
            [$this->makeProduct('p1', 'Widget', 'unknown-brand', 'c1', 1000)],
            1,
            [new Category('c1', 'Electronics', 'electronics')],
            []
        );
        $service = new CatalogReadService($repo);

        $response = $service->searchProducts(new SearchFilters(), 1, 12);

        $this->assertSame('', $response->getItems()[0]->getBrandName());
        $this->assertSame('Electronics', $response->getItems()[0]->getCategoryName());
    }

    public function testGetFilterFacetsReturnsCategoriesBrandsAndPriceRange(): void
    {
        $categories = [
            new Category('c1', 'Electronics', 'electronics'),
            new Category('c2', 'Books', 'books'),
        ];
        $brands = [
            new Brand('b1', 'Acme'),
            new Brand('b2', 'Globex'),
        ];
        $repo = new StubProductRepository([], 0, $categories, $brands);
        $repo->priceMin = 500;
        $repo->priceMax = 9999;

        $service = new CatalogReadService($repo);
        $response = $service->getFilterFacets();

        $this->assertSame($categories, $response->getCategories());
        $this->assertSame($brands, $response->getBrands());
        $this->assertSame(500, $response->getPriceMin());
        $this->assertSame(9999, $response->getPriceMax());
    }

    public function testGetFilterFacetsForEmptyRepository(): void
    {
        $service = new CatalogReadService(new StubProductRepository());

        $response = $service->getFilterFacets();

        $this->assertSame([], $response->getCategories());
        $this->assertSame([], $response->getBrands());
        $this->assertSame(0, $response->getPriceMin());
        $this->assertSame(0, $response->getPriceMax());
    }

    private function makeProduct(string $id, string $name, string $brandId, string $categoryId, int $price): Product
    {
        return new Product(
            new ProductId($id),
            $name,
            $brandId,
            $categoryId,
            new Price($price, 'USD'),
            'https://example.com/' . $id . '.jpg',
            'Summary for ' . $name
        );
    }
}
