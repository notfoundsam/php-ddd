<?php

declare(strict_types=1);

namespace Audience\Partner\Infrastructure\Security;

use SharedKernel\Domain\Security\SecurityConfigRegistryInterface;

final class PartnerSecurityConfigRegistry implements SecurityConfigRegistryInterface
{
    private PartnerSecurityConfig $config;

    public function __construct(PartnerSecurityConfig $config)
    {
        $this->config = $config;
    }

    public function getSecurityConfigs(): array
    {
        return [$this->config];
    }
}
