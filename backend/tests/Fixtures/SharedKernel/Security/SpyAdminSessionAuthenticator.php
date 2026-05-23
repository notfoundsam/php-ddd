<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\SessionAuthenticator\AdminSessionAuthenticatorInterface;
use SharedKernel\Domain\Security\AuthenticatedUser;

final class SpyAdminSessionAuthenticator implements AdminSessionAuthenticatorInterface
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
}
