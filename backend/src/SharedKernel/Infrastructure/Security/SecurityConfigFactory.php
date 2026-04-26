<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Domain\Security\SecurityConfigRegistryInterface;

final class SecurityConfigFactory
{
    /** @var SecurityConfigRegistryInterface[] */
    private array $registries;

    /**
     * @param SecurityConfigRegistryInterface[] $registries
     */
    public function __construct(array $registries)
    {
        $this->registries = $registries;
    }

    public function __invoke(): SecurityConfigInterface
    {
        $configs = [];
        foreach ($this->registries as $registry) {
            foreach ($registry->getSecurityConfigs() as $config) {
                $configs[] = $config;
            }
        }

        return new CompositeSecurityConfig($configs);
    }
}
