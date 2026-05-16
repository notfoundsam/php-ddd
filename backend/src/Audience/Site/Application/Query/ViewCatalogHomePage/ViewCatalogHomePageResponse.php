<?php

declare(strict_types=1);

namespace Audience\Site\Application\Query\ViewCatalogHomePage;

use Catalog\Application\ReadModel\FilterFacets;
use Catalog\Application\ReadModel\ProductSearchResult;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;

final class ViewCatalogHomePageResponse implements QueryResponseInterface
{
    private ProductSearchResult $products;
    private FilterFacets $facets;

    public function __construct(ProductSearchResult $products, FilterFacets $facets)
    {
        $this->products = $products;
        $this->facets = $facets;
    }

    public function getProducts(): ProductSearchResult
    {
        return $this->products;
    }

    public function getFacets(): FilterFacets
    {
        return $this->facets;
    }
}
