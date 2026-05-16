<?php

declare(strict_types=1);

namespace Audience\Site\Infrastructure\Security;

use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsQuery;
use Audience\Site\Application\Query\ViewCatalogHomePage\ViewCatalogHomePageQuery;
use SharedKernel\Domain\Security\SecurityConfigInterface;

final class SiteSecurityConfig implements SecurityConfigInterface
{
    public function getCommandPermissions(): array
    {
        return [];
    }

    public function getQueryPermissions(): array
    {
        return [
            ViewCatalogHomePageQuery::class => null,
            SearchCatalogProductsQuery::class => null,
        ];
    }

    public function getRolePermissions(): array
    {
        return [];
    }
}
