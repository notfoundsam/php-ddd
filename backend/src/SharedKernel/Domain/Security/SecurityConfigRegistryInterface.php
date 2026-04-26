<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

interface SecurityConfigRegistryInterface
{
    /**
     * Return the security configurations contributed by this audience.
     *
     * @return SecurityConfigInterface[]
     */
    public function getSecurityConfigs(): array;
}
