<?php

declare(strict_types=1);

namespace Audience\Site\Application\Query\SearchCatalogProducts;

use Catalog\Application\ReadModel\ProductSearchResult;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;

/**
 * Customer-audience response for SearchCatalogProducts.
 *
 * Wraps the Catalog read model so the audience contract stays stable when the BC evolves,
 * and so audience-specific metadata (cache hints, pagination flags, etc.) can be added
 * here without leaking into Catalog.
 */
final class SearchCatalogProductsResponse implements QueryResponseInterface
{
    private ProductSearchResult $result;

    public function __construct(ProductSearchResult $result)
    {
        $this->result = $result;
    }

    public function getResult(): ProductSearchResult
    {
        return $this->result;
    }
}
