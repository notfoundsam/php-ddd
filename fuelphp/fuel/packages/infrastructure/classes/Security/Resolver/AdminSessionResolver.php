<?php

declare(strict_types=1);

namespace Infrastructure\Security\Resolver;

use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolver\AdminUserResolverInterface;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Infrastructure\Security\SessionUserResolver;

final class AdminSessionResolver extends SessionUserResolver implements AdminUserResolverInterface
{
    public function __construct(
        SessionAuthenticatorInterface $session,
        AdminRememberMeServiceInterface $rememberMe,
        AdminUserRepositoryInterface $users
    ) {
        parent::__construct($session, $rememberMe, $users);
    }

    protected function expectedUserType(): string
    {
        return UserType::ADMIN;
    }
}
