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
        $session->set('user_type', $user->getType());
        $session->rotate();
    }

    public function logout(): void
    {
        $session = $this->session();
        $session->delete('user_id');
        $session->delete('user_type');
        $session->rotate();
    }

    public function getCurrentUserId(): ?string
    {
        $userId = $this->session()->get('user_id');
        return $userId === null ? null : (string)$userId;
    }

    public function getCurrentUserType(): ?string
    {
        $type = $this->session()->get('user_type');
        return $type === null ? null : (string)$type;
    }

    private function session(): Session_Driver
    {
        if ($this->sessionInstance === null) {
            $this->sessionInstance = Session::instance();
        }
        return $this->sessionInstance;
    }
}
