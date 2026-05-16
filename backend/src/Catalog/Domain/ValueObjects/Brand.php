<?php

declare(strict_types=1);

namespace Catalog\Domain\ValueObjects;

use InvalidArgumentException;

final class Brand
{
    private string $id;
    private string $name;

    public function __construct(string $id, string $name)
    {
        $id = trim($id);
        $name = trim($name);
        if ($id === '') {
            throw new InvalidArgumentException('Brand id cannot be empty.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Brand name cannot be empty.');
        }
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
