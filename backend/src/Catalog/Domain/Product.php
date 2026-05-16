<?php

declare(strict_types=1);

namespace Catalog\Domain;

use Catalog\Domain\ValueObjects\Price;
use Catalog\Domain\ValueObjects\ProductId;

final class Product
{
    private ProductId $id;
    private string $name;
    private string $brandId;
    private string $categoryId;
    private Price $price;
    private string $imageUrl;
    private string $summary;

    public function __construct(
        ProductId $id,
        string $name,
        string $brandId,
        string $categoryId,
        Price $price,
        string $imageUrl,
        string $summary
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->brandId = $brandId;
        $this->categoryId = $categoryId;
        $this->price = $price;
        $this->imageUrl = $imageUrl;
        $this->summary = $summary;
    }

    public function getId(): ProductId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBrandId(): string
    {
        return $this->brandId;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function getPrice(): Price
    {
        return $this->price;
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
