<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

interface AuthorizationServiceInterface
{
    public function isAllowed(AuthenticatedUser $user, string $permission): bool;
}
