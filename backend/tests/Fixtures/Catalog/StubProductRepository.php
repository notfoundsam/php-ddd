<?php

declare(strict_types=1);

namespace Tests\Fixtures\Catalog;

use Catalog\Application\ReadModel\SearchFilters;
use Catalog\Domain\Product;
use Catalog\Domain\Repository\ProductRepositoryInterface;
use Catalog\Domain\ValueObjects\Brand;
use Catalog\Domain\ValueObjects\Category;

final class StubProductRepository implements ProductRepositoryInterface
{
    /** @var Product[] */
    public array $products;
    public int $total;
    /** @var Category[] */
    public array $categories;
    /** @var Brand[] */
    public array $brands;
    public int $priceMin = 0;
    public int $priceMax = 0;

    public ?SearchFilters $lastFilters = null;
    public ?int $lastPage = null;
    public ?int $lastPerPage = null;

    /**
     * @param Product[] $products
     * @param Category[] $categories
     * @param Brand[] $brands
     */
    public function __construct(array $products = [], int $total = 0, array $categories = [], array $brands = [])
    {
        $this->products = $products;
        $this->total = $total;
        $this->categories = $categories;
        $this->brands = $brands;
    }

    public function findByFilters(SearchFilters $filters, int $page, int $perPage): array
    {
        $this->lastFilters = $filters;
        $this->lastPage = $page;
        $this->lastPerPage = $perPage;
        return ['products' => $this->products, 'total' => $this->total];
    }

    public function getFacets(): array
    {
        return [
            'categories' => $this->categories,
            'brands' => $this->brands,
            'priceMin' => $this->priceMin,
            'priceMax' => $this->priceMax,
        ];
    }
}
