<?php

declare(strict_types=1);

namespace Audience\Site\Infrastructure\CqrsMessageBus;

use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsQuery;
use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsQueryHandler;
use Audience\Site\Application\Query\ViewCatalogHomePage\ViewCatalogHomePageQuery;
use Audience\Site\Application\Query\ViewCatalogHomePage\ViewCatalogHomePageQueryHandler;
use SharedKernel\Infrastructure\CqrsMessageBus\QueryHandlerRegistryInterface;

final class SiteQueryHandlerRegistry implements QueryHandlerRegistryInterface
{
    public function getQueryHandlers(): array
    {
        return [
            ViewCatalogHomePageQuery::class => ViewCatalogHomePageQueryHandler::class,
            SearchCatalogProductsQuery::class => SearchCatalogProductsQueryHandler::class,
        ];
    }
}
