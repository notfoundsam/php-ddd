<?php

declare(strict_types=1);

namespace Catalog\Application\Service;

use Catalog\Application\ReadModel\FilterFacets;
use Catalog\Application\ReadModel\ProductListItem;
use Catalog\Application\ReadModel\ProductSearchResult;
use Catalog\Application\ReadModel\SearchFilters;
use Catalog\Domain\Product;
use Catalog\Domain\Repository\ProductRepositoryInterface;
use Catalog\Domain\ValueObjects\Brand;
use Catalog\Domain\ValueObjects\Category;

/**
 * Public application-layer API of the Catalog bounded context for read operations.
 *
 * Audience handlers (e.g. Audience\Site\...) call this service directly — there is no
 * CQRS bus dispatch at this boundary, because cross-cutting decorators (throttle/security/log)
 * already ran at the audience query that called us. Inside the BC, this is just a typed
 * facade over the repository plus presentation-shape mapping.
 */
final class CatalogReadService
{
    private const PER_PAGE_FALLBACK = 12;
    private const PAGE_HARD_CAP = 10000;

    private ProductRepositoryInterface $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function searchProducts(SearchFilters $filters, int $page, int $perPage): ProductSearchResult
    {
        $page = $page < 1 ? 1 : $page;
        // Cap the page index before it reaches the repository. The in-memory repo doesn't care,
        // but a DB-backed implementation would translate ?page=99999999 into a multi-billion-row
        // OFFSET. Hard ceiling keeps that input from ever hitting the storage layer.
        $page = $page > self::PAGE_HARD_CAP ? self::PAGE_HARD_CAP : $page;
        $perPage = $perPage < 1 ? self::PER_PAGE_FALLBACK : $perPage;

        $result = $this->repository->findByFilters($filters, $page, $perPage);

        $facets = $this->repository->getFacets();
        $brandsById = $this->indexBrandsById($facets['brands']);
        $categoriesById = $this->indexCategoriesById($facets['categories']);

        $items = [];
        foreach ($result['products'] as $product) {
            $items[] = $this->toListItem($product, $brandsById, $categoriesById);
        }

        return new ProductSearchResult($items, $result['total'], $page, $perPage);
    }

    public function getFilterFacets(): FilterFacets
    {
        $facets = $this->repository->getFacets();

        return new FilterFacets(
            $facets['categories'],
            $facets['brands'],
            $facets['priceMin'],
            $facets['priceMax']
        );
    }

    /**
     * @param array<string, Brand> $brandsById
     * @param array<string, Category> $categoriesById
     */
    private function toListItem(Product $product, array $brandsById, array $categoriesById): ProductListItem
    {
        $brand = $brandsById[$product->getBrandId()] ?? null;
        $category = $categoriesById[$product->getCategoryId()] ?? null;

        return new ProductListItem(
            $product->getId()->toString(),
            $product->getName(),
            $brand !== null ? $brand->getName() : '',
            $category !== null ? $category->getName() : '',
            $product->getPrice()->format(),
            $product->getImageUrl(),
            $product->getSummary()
        );
    }

    /**
     * @param Brand[] $brands
     * @return array<string, Brand>
     */
    private function indexBrandsById(array $brands): array
    {
        $result = [];
        foreach ($brands as $brand) {
            $result[$brand->getId()] = $brand;
        }
        return $result;
    }

    /**
     * @param Category[] $categories
     * @return array<string, Category>
     */
    private function indexCategoriesById(array $categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            $result[$category->getId()] = $category;
        }
        return $result;
    }
}
