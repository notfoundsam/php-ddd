<?php

declare(strict_types=1);

namespace Catalog\Application\ReadModel;

use Catalog\Domain\ValueObjects\Brand;
use Catalog\Domain\ValueObjects\Category;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;

final class FilterFacets implements QueryResponseInterface
{
    /** @var Category[] */
    private array $categories;
    /** @var Brand[] */
    private array $brands;
    private int $priceMin;
    private int $priceMax;

    /**
     * @param Category[] $categories
     * @param Brand[] $brands
     */
    public function __construct(array $categories, array $brands, int $priceMin, int $priceMax)
    {
        $this->categories = array_values($categories);
        $this->brands = array_values($brands);
        $this->priceMin = max(0, $priceMin);
        $this->priceMax = max($this->priceMin, $priceMax);
    }

    /** @return Category[] */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /** @return Brand[] */
    public function getBrands(): array
    {
        return $this->brands;
    }

    public function getPriceMin(): int
    {
        return $this->priceMin;
    }

    public function getPriceMax(): int
    {
        return $this->priceMax;
    }
}
