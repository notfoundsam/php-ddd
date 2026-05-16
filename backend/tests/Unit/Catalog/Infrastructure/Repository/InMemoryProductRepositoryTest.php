<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Infrastructure\Repository;

use Catalog\Application\ReadModel\SearchFilters;
use Catalog\Domain\Product;
use Catalog\Infrastructure\Repository\InMemoryProductRepository;
use PHPUnit\Framework\TestCase;

class InMemoryProductRepositoryTest extends TestCase
{
    public function testNoFiltersReturnsAllProductsPaginated(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(new SearchFilters(), 1, 12);

        $this->assertSame(20, $result['total']);
        $this->assertCount(12, $result['products']);
    }

    public function testResultsAreSortedAlphabeticallyByName(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(new SearchFilters(), 1, 100);
        $names = array_map(static fn (Product $p): string => $p->getName(), $result['products']);
        $sorted = $names;
        sort($sorted);

        $this->assertSame($sorted, $names);
    }

    public function testFilterByCategoryReturnsOnlyMatchingProducts(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(new SearchFilters(['cat-books']), 1, 100);

        $this->assertSame(3, $result['total']);
        foreach ($result['products'] as $product) {
            $this->assertSame('cat-books', $product->getCategoryId());
        }
    }

    public function testFilterByBrandReturnsOnlyMatchingProducts(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(new SearchFilters([], ['brand-acme']), 1, 100);

        $this->assertGreaterThan(0, $result['total']);
        foreach ($result['products'] as $product) {
            $this->assertSame('brand-acme', $product->getBrandId());
        }
    }

    public function testFilterByPriceRangeIsInclusive(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(new SearchFilters([], [], 2999, 4999), 1, 100);

        $this->assertGreaterThan(0, $result['total']);
        foreach ($result['products'] as $product) {
            $amount = $product->getPrice()->getAmount();
            $this->assertGreaterThanOrEqual(2999, $amount);
            $this->assertLessThanOrEqual(4999, $amount);
        }
    }

    public function testFilterBySearchTermIsCaseInsensitive(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(new SearchFilters([], [], null, null, 'KEYBOARD'), 1, 100);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Mechanical Keyboard', $result['products'][0]->getName());
    }

    public function testMultipleFiltersCombineAsAnd(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(
            new SearchFilters(['cat-electronics'], ['brand-globex']),
            1,
            100
        );

        $this->assertGreaterThan(0, $result['total']);
        foreach ($result['products'] as $product) {
            $this->assertSame('cat-electronics', $product->getCategoryId());
            $this->assertSame('brand-globex', $product->getBrandId());
        }
    }

    public function testPaginationSlicesResults(): void
    {
        $repo = new InMemoryProductRepository();

        $page1 = $repo->findByFilters(new SearchFilters(), 1, 5);
        $page2 = $repo->findByFilters(new SearchFilters(), 2, 5);

        $this->assertCount(5, $page1['products']);
        $this->assertCount(5, $page2['products']);
        $this->assertNotEquals(
            $page1['products'][0]->getId()->toString(),
            $page2['products'][0]->getId()->toString()
        );
    }

    public function testPageBeyondTotalReturnsEmptyButPreservesTotal(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(new SearchFilters(), 99, 12);

        $this->assertSame([], $result['products']);
        $this->assertSame(20, $result['total']);
    }

    public function testEmptyResultForUnmatchedFilter(): void
    {
        $repo = new InMemoryProductRepository();

        $result = $repo->findByFilters(new SearchFilters(['cat-nonexistent']), 1, 12);

        $this->assertSame([], $result['products']);
        $this->assertSame(0, $result['total']);
    }

    public function testFacetsReturnSeededCategoriesAndBrands(): void
    {
        $repo = new InMemoryProductRepository();

        $facets = $repo->getFacets();

        $this->assertCount(5, $facets['categories']);
        $this->assertCount(6, $facets['brands']);
    }

    public function testFacetsPriceRangeReflectsSeededData(): void
    {
        $repo = new InMemoryProductRepository();

        $facets = $repo->getFacets();

        $this->assertSame(1499, $facets['priceMin']);
        $this->assertSame(29999, $facets['priceMax']);
    }
}
