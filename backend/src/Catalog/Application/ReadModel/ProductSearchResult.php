<?php

declare(strict_types=1);

namespace Catalog\Application\ReadModel;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;

final class ProductSearchResult implements QueryResponseInterface
{
    /** @var ProductListItem[] */
    private array $items;
    private int $total;
    private int $page;
    private int $perPage;
    private int $totalPages;

    /**
     * @param ProductListItem[] $items
     */
    public function __construct(array $items, int $total, int $page, int $perPage)
    {
        $this->items = array_values($items);
        $this->total = max(0, $total);
        $this->page = max(1, $page);
        $this->perPage = max(1, $perPage);
        $this->totalPages = $this->total === 0 ? 0 : (int) ceil($this->total / $this->perPage);
    }

    /** @return ProductListItem[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }
}
