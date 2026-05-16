<?php

use Catalog\Domain\Repository\ProductRepositoryInterface;
use Catalog\Infrastructure\Repository\InMemoryProductRepository;

return [
    ProductRepositoryInterface::class => DI\autowire(InMemoryProductRepository::class),
];
