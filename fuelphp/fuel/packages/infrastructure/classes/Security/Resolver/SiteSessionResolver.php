<?php

declare(strict_types=1);

namespace Infrastructure\Security\Resolver;

use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\RememberMe\SiteRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolverInterface;

final class SiteSessionResolver implements UserResolverInterface
{
    private SessionAuthenticatorInterface $session;

    private SiteRememberMeServiceInterface $rememberMe;

    private SiteUserRepositoryInterface $users;

    public function __construct(
        SessionAuthenticatorInterface $session,
        SiteRememberMeServiceInterface $rememberMe,
        SiteUserRepositoryInterface $users
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
