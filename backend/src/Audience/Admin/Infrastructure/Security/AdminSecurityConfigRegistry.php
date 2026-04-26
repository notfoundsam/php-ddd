<?php

declare(strict_types=1);

namespace Audience\Admin\Infrastructure\Security;

use SharedKernel\Domain\Security\SecurityConfigRegistryInterface;

final class AdminSecurityConfigRegistry implements SecurityConfigRegistryInterface
{
    private AdminSecurityConfig $config;

    public function __construct(AdminSecurityConfig $config)
    {
        $this->config = $config;
    }

    public function getSecurityConfigs(): array
    {
        return [$this->config];
    }
}
