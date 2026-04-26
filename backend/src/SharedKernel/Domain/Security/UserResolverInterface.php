<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

interface UserResolverInterface
{
    /**
     * Resolve the authenticated user for the current request, session, or token.
     * Implementations are framework-specific (FuelPHP session, Cognito JWT, Laravel auth, etc.).
     *
     * @return AuthenticatedUser|null Null when no user is authenticated.
     */
    public function resolve(): ?AuthenticatedUser;
}
