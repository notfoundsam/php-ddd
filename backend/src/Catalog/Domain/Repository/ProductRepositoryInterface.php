<?php

declare(strict_types=1);

namespace Catalog\Domain\Repository;

use Catalog\Application\ReadModel\SearchFilters;

interface ProductRepositoryInterface
{
    /**
     * Returns matching products plus the total count for pagination.
     *
     * @return array{products: \Catalog\Domain\Product[], total: int}
     */
    public function findByFilters(SearchFilters $filters, int $page, int $perPage): array;

    /**
     * Returns snapshot of available facets — categories, brands, and global price bounds.
     *
     * @return array{
     *     categories: \Catalog\Domain\ValueObjects\Category[],
     *     brands: \Catalog\Domain\ValueObjects\Brand[],
     *     priceMin: int,
     *     priceMax: int
     * }
     */
    public function getFacets(): array;
}
