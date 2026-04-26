<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\AuthorizationServiceInterface;
use SharedKernel\Domain\Security\SecurityConfigInterface;

final class ConfigAuthorizationService implements AuthorizationServiceInterface
{
    public const PERMISSION_WILDCARD = '*';

    private SecurityConfigInterface $config;

    public function __construct(SecurityConfigInterface $config)
    {
        $this->config = $config;
    }

    public function isAllowed(AuthenticatedUser $user, string $permission): bool
    {
        $rolePermissions = $this->config->getRolePermissions();

        foreach ($user->getRoles() as $role) {
            $granted = $rolePermissions[$role] ?? [];
            if (in_array(self::PERMISSION_WILDCARD, $granted, true)) {
                return true;
            }
            if (in_array($permission, $granted, true)) {
                return true;
            }
        }

        return false;
    }
}
