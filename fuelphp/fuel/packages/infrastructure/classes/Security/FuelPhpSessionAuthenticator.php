<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use Fuel\Core\Session;
use Fuel\Core\Session_Driver;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;

abstract class FuelPhpSessionAuthenticator implements SessionAuthenticatorInterface
{
    private string $cookieName;

    private ?Session_Driver $sessionInstance = null;

    public function __construct(string $cookieName)
    {
        $this->cookieName = $cookieName;
    }

    final public function login(AuthenticatedUser $user): void
    {
        $session = $this->session();
        $session->set('user_id', $user->getId());
        $session->rotate();
    }

    final public function logout(): void
    {
        $session = $this->session();
        $session->delete('user_id');
        $session->rotate();
    }

    final public function getCurrentUserId(): ?string
    {
        $userId = $this->session()->get('user_id');
        return $userId === null ? null : (string)$userId;
    }

    private function session()
    {
        if ($this->sessionInstance === null) {
            $existing = Session::instance($this->cookieName);
            if ($existing !== false) {
                $this->sessionInstance = $existing;
            } else {
                // Secure=true and SameSite=Lax are applied by the global Cookie::set
                // override (fuelphp/fuel/app/classes/cookie.php), which the FuelPHP
                // session driver delegates to. Do NOT duplicate them here, or a future
                // change to the global policy will silently bypass them per-audience.
                $this->sessionInstance = Session::forge([
                    'driver' => 'redis',
                    'encrypt_cookie' => false,
                    'cookie_http_only' => true,
                    'redis' => [
                        'cookie_name' => $this->cookieName,
                        'database' => 'default',
                    ],
                ]);
            }
        }
        return $this->sessionInstance;
    }
}
