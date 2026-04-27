<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

interface SecurityConfigInterface
{
    /**
     * Map of command FQCN to the required permission name.
     * A null value declares the command as explicitly public (no authentication required).
     * A missing entry causes SecurityConfigurationException at dispatch time (fail closed).
     *
     * @return array<class-string, string|null>
     */
    public function getCommandPermissions(): array;

    /**
     * Map of query FQCN to the required permission name. Same semantics as command permissions.
     *
     * @return array<class-string, string|null>
     */
    public function getQueryPermissions(): array;

    /**
     * Map of role name to the list of permissions that role grants.
     * The wildcard '*' grants every permission.
     *
     * @return array<string, array<string>>
     */
    public function getRolePermissions(): array;
}
