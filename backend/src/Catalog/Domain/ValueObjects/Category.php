<?php

declare(strict_types=1);

namespace Catalog\Domain\ValueObjects;

use InvalidArgumentException;

final class Category
{
    private string $id;
    private string $name;
    private string $slug;

    public function __construct(string $id, string $name, string $slug)
    {
        $id = trim($id);
        $name = trim($name);
        $slug = trim($slug);
        if ($id === '') {
            throw new InvalidArgumentException('Category id cannot be empty.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Category name cannot be empty.');
        }
        if ($slug === '') {
            throw new InvalidArgumentException('Category slug cannot be empty.');
        }
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
