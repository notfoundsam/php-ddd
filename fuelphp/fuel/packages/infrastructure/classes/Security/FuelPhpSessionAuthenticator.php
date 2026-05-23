<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use Fuel\Core\Session;
use Fuel\Core\Session_Driver;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;

final class FuelPhpSessionAuthenticator implements SessionAuthenticatorInterface
{
    private ?Session_Driver $sessionInstance = null;

    public function login(AuthenticatedUser $user): void
    {
        $session = $this->session();
        $session->set('user_id', $user->getId());
        $session->rotate();
    }

    public function logout(): void
    {
        $session = $this->session();
        $session->delete('user_id');
        $session->rotate();
    }

    public function getCurrentUserId(): ?string
    {
        $userId = $this->session()->get('user_id');
        return $userId === null ? null : (string)$userId;
    }

    private function session(): Session_Driver
    {
        if ($this->sessionInstance === null) {
            // Default singleton — driver, cookie name, encrypt/HttpOnly read from app/config/session.php.
            $this->sessionInstance = Session::instance();
        }
        return $this->sessionInstance;
    }
}
