<?php

declare(strict_types=1);

namespace Catalog\Application\ReadModel;

final class SearchFilters
{
    /** @var string[] */
    private array $categoryIds;
    /** @var string[] */
    private array $brandIds;
    private ?int $priceMin;
    private ?int $priceMax;
    private ?string $searchTerm;

    /**
     * @param string[] $categoryIds
     * @param string[] $brandIds
     */
    public function __construct(
        array $categoryIds = [],
        array $brandIds = [],
        ?int $priceMin = null,
        ?int $priceMax = null,
        ?string $searchTerm = null
    ) {
        $this->categoryIds = self::stringValues($categoryIds);
        $this->brandIds = self::stringValues($brandIds);
        $this->priceMin = $priceMin;
        $this->priceMax = $priceMax;
        $this->searchTerm = $searchTerm !== null && trim($searchTerm) !== '' ? trim($searchTerm) : null;
    }

    /**
     * @param array<int, mixed> $values
     * @return string[]
     */
    private static function stringValues(array $values): array
    {
        $result = [];
        foreach ($values as $v) {
            if (!is_string($v) || $v === '') {
                continue;
            }
            $result[] = $v;
        }
        return $result;
    }

    /** @return string[] */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    /** @return string[] */
    public function getBrandIds(): array
    {
        return $this->brandIds;
    }

    public function getPriceMin(): ?int
    {
        return $this->priceMin;
    }

    public function getPriceMax(): ?int
    {
        return $this->priceMax;
    }

    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }

    public function isEmpty(): bool
    {
        return $this->categoryIds === []
            && $this->brandIds === []
            && $this->priceMin === null
            && $this->priceMax === null
            && $this->searchTerm === null;
    }

    /**
     * Roundtrips into the URL query string.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        if ($this->categoryIds !== []) {
            $result['category'] = $this->categoryIds;
        }
        if ($this->brandIds !== []) {
            $result['brand'] = $this->brandIds;
        }
        if ($this->priceMin !== null) {
            $result['price_min'] = $this->priceMin;
        }
        if ($this->priceMax !== null) {
            $result['price_max'] = $this->priceMax;
        }
        if ($this->searchTerm !== null) {
            $result['q'] = $this->searchTerm;
        }
        return $result;
    }
}
