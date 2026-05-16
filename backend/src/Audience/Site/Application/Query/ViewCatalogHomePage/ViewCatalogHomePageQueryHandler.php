<?php

declare(strict_types=1);

namespace Audience\Site\Application\Query\ViewCatalogHomePage;

use Catalog\Application\Service\CatalogReadService;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryHandlerInterface;

final class ViewCatalogHomePageQueryHandler implements QueryHandlerInterface
{
    private CatalogReadService $catalog;

    public function __construct(CatalogReadService $catalog)
    {
        $this->catalog = $catalog;
    }

    public function __invoke(ViewCatalogHomePageQuery $query): ViewCatalogHomePageResponse
    {
        $products = $this->catalog->searchProducts(
            $query->getFilters(),
            $query->getPage(),
            $query->getPerPage()
        );
        $facets = $this->catalog->getFilterFacets();

        return new ViewCatalogHomePageResponse($products, $facets);
    }
}
