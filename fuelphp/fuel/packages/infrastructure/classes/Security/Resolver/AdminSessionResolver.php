<?php

declare(strict_types=1);

namespace Infrastructure\Security\Resolver;

use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\AdminSessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserResolverInterface;

final class AdminSessionResolver implements UserResolverInterface
{
    private AdminSessionAuthenticatorInterface $session;

    private AdminRememberMeServiceInterface $rememberMe;

    private AdminUserRepositoryInterface $users;

    public function __construct(
        AdminSessionAuthenticatorInterface $session,
        AdminRememberMeServiceInterface $rememberMe,
        AdminUserRepositoryInterface $users
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
