<?php

declare(strict_types=1);

namespace Infrastructure\Security\Resolver;

use SharedKernel\Domain\Security\RememberMe\SiteRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolver\SiteUserResolverInterface;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Infrastructure\Security\SessionUserResolver;

final class SiteSessionResolver extends SessionUserResolver implements SiteUserResolverInterface
{
    public function __construct(
        SessionAuthenticatorInterface $session,
        SiteRememberMeServiceInterface $rememberMe,
        SiteUserRepositoryInterface $users
    ) {
        parent::__construct($session, $rememberMe, $users);
    }

    protected function expectedUserType(): string
    {
        return UserType::CUSTOMER;
    }
}
