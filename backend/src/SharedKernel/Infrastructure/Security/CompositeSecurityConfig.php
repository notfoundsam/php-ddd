<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\Exception\SecurityConfigurationException;
use SharedKernel\Domain\Security\SecurityConfigInterface;

final class CompositeSecurityConfig implements SecurityConfigInterface
{
    /** @var SecurityConfigInterface[] */
    private array $configs;

    /** @var array<class-string, string|null>|null */
    private ?array $commandPermissions = null;

    /** @var array<class-string, string|null>|null */
    private ?array $queryPermissions = null;

    /** @var array<string, array<string>>|null */
    private ?array $rolePermissions = null;

    /**
     * @param SecurityConfigInterface[] $configs
     */
    public function __construct(array $configs)
    {
        $this->configs = $configs;
    }

    public function getCommandPermissions(): array
    {
        if ($this->commandPermissions === null) {
            $this->commandPermissions = $this->mergeOperationPermissions(true);
        }

        return $this->commandPermissions;
    }

    public function getQueryPermissions(): array
    {
        if ($this->queryPermissions === null) {
            $this->queryPermissions = $this->mergeOperationPermissions(false);
        }

        return $this->queryPermissions;
    }

    public function getRolePermissions(): array
    {
        if ($this->rolePermissions === null) {
            $merged = [];
            foreach ($this->configs as $config) {
                foreach ($config->getRolePermissions() as $role => $permissions) {
                    if (!isset($merged[$role])) {
                        $merged[$role] = [];
                    }
                    foreach ($permissions as $permission) {
                        $merged[$role][] = $permission;
                    }
                }
            }
            foreach ($merged as $role => $permissions) {
                $merged[$role] = array_values(array_unique($permissions));
            }
            $this->rolePermissions = $merged;
        }

        return $this->rolePermissions;
    }

    /**
     * @return array<class-string, string|null>
     */
    private function mergeOperationPermissions(bool $forCommands): array
    {
        $merged = [];
        foreach ($this->configs as $config) {
            $entries = $forCommands ? $config->getCommandPermissions() : $config->getQueryPermissions();
            foreach ($entries as $class => $permission) {
                if (array_key_exists($class, $merged)) {
                    throw $forCommands
                        ? SecurityConfigurationException::duplicateCommandEntry($class)
                        : SecurityConfigurationException::duplicateQueryEntry($class);
                }
                $merged[$class] = $permission;
            }
        }

        return $merged;
    }
}
