<?php

declare(strict_types=1);

namespace Catalog\Application\ReadModel;

final class ProductListItem
{
    private string $id;
    private string $name;
    private string $brandName;
    private string $categoryName;
    private string $priceFormatted;
    private string $imageUrl;
    private string $summary;

    public function __construct(
        string $id,
        string $name,
        string $brandName,
        string $categoryName,
        string $priceFormatted,
        string $imageUrl,
        string $summary
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->brandName = $brandName;
        $this->categoryName = $categoryName;
        $this->priceFormatted = $priceFormatted;
        $this->imageUrl = $imageUrl;
        $this->summary = $summary;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function getCategoryName(): string
    {
        return $this->categoryName;
    }

    public function getPriceFormatted(): string
    {
        return $this->priceFormatted;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }
}
