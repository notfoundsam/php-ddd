<?php

declare(strict_types=1);

namespace Infrastructure\Security\Resolver;

use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserResolverInterface;
use SharedKernel\Domain\Security\UserType;

final class AdminSessionResolver implements UserResolverInterface
{
    private SessionAuthenticatorInterface $session;

    private AdminRememberMeServiceInterface $rememberMe;

    private AdminUserRepositoryInterface $users;

    public function __construct(
        SessionAuthenticatorInterface $session,
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
            // Reject blobs from another audience: independent PK sequences across user tables
            // mean an unguarded findById would silently resolve a foreign id (see ADR-014).
            if ($this->session->getCurrentUserType() !== UserType::ADMIN) {
                return null;
            }
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
