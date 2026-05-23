<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\RememberMe\RememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\UserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolverInterface;

abstract class SessionUserResolver implements UserResolverInterface
{
    private SessionAuthenticatorInterface $session;

    private RememberMeServiceInterface $rememberMe;

    private UserRepositoryInterface $users;

    public function __construct(
        SessionAuthenticatorInterface $session,
        RememberMeServiceInterface $rememberMe,
        UserRepositoryInterface $users
    ) {
        $this->session = $session;
        $this->rememberMe = $rememberMe;
        $this->users = $users;
    }

    final public function resolve(): ?AuthenticatedUser
    {
        $userId = $this->session->getCurrentUserId();
        if ($userId !== null) {
            // Reject blobs from another audience: independent PK sequences across user tables
            // mean an unguarded findById would silently resolve a foreign id (see ADR-014).
            if ($this->session->getCurrentUserType() !== $this->expectedUserType()) {
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

    abstract protected function expectedUserType(): string;
}
