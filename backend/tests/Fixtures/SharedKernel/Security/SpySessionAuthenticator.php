<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;

final class SpySessionAuthenticator implements SessionAuthenticatorInterface
{
    public ?AuthenticatedUser $loggedIn = null;

    public bool $loggedOut = false;

    public function login(AuthenticatedUser $user): void
    {
        $this->loggedIn = $user;
    }

    public function logout(): void
    {
        $this->loggedOut = true;
    }

    public function getCurrentUserId(): ?string
    {
        return $this->loggedIn === null ? null : $this->loggedIn->getId();
    }

    public function getCurrentUserType(): ?string
    {
        return $this->loggedIn === null ? null : $this->loggedIn->getType();
    }
}
