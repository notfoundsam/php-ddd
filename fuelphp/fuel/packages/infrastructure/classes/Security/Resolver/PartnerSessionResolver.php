<?php

declare(strict_types=1);

namespace Infrastructure\Security\Resolver;

use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\RememberMe\PartnerRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\PartnerSessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolverInterface;

final class PartnerSessionResolver implements UserResolverInterface
{
    private PartnerSessionAuthenticatorInterface $session;

    private PartnerRememberMeServiceInterface $rememberMe;

    private PartnerUserRepositoryInterface $users;

    public function __construct(
        PartnerSessionAuthenticatorInterface $session,
        PartnerRememberMeServiceInterface $rememberMe,
        PartnerUserRepositoryInterface $users
    ) {
        $this->session = $session;
        $this->rememberMe = $rememberMe;
        $this->users = $users;
    }

    public function resolve(): ?AuthenticatedUser
    {
        $userId = $this->session->getCurrentUserId();
        if ($userId !== null) {
            return $this->users->findById($userId);
        }

        $user = $this->rememberMe->tryReanimate();
        if ($user === null) {
            return null;
        }
        $this->session->login($user);
        return $user;
    }
}
