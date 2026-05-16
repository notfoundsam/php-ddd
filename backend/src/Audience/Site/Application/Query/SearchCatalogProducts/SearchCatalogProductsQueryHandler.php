<?php

declare(strict_types=1);

namespace Audience\Site\Application\Query\SearchCatalogProducts;

use Catalog\Application\Service\CatalogReadService;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryHandlerInterface;

final class SearchCatalogProductsQueryHandler implements QueryHandlerInterface
{
    private CatalogReadService $catalog;

    public function __construct(CatalogReadService $catalog)
    {
        $this->catalog = $catalog;
    }

    public function __invoke(SearchCatalogProductsQuery $query): SearchCatalogProductsResponse
    {
        $result = $this->catalog->searchProducts(
            $query->getFilters(),
            $query->getPage(),
            $query->getPerPage()
        );
        return new SearchCatalogProductsResponse($result);
    }
}
