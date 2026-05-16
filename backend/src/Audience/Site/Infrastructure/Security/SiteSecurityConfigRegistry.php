<?php

declare(strict_types=1);

namespace Audience\Site\Infrastructure\Security;

use SharedKernel\Domain\Security\SecurityConfigRegistryInterface;

final class SiteSecurityConfigRegistry implements SecurityConfigRegistryInterface
{
    private SiteSecurityConfig $config;

    public function __construct(SiteSecurityConfig $config)
    {
        $this->config = $config;
    }

    public function getSecurityConfigs(): array
    {
        return [$this->config];
    }
}
