<?php

declare(strict_types=1);

namespace Infrastructure\Security\Resolver;

use SharedKernel\Domain\Security\RememberMe\PartnerRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolver\PartnerUserResolverInterface;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Infrastructure\Security\SessionUserResolver;

final class PartnerSessionResolver extends SessionUserResolver implements PartnerUserResolverInterface
{
    public function __construct(
        SessionAuthenticatorInterface $session,
        PartnerRememberMeServiceInterface $rememberMe,
        PartnerUserRepositoryInterface $users
    ) {
        parent::__construct($session, $rememberMe, $users);
    }

    protected function expectedUserType(): string
    {
        return UserType::PARTNER;
    }
}
