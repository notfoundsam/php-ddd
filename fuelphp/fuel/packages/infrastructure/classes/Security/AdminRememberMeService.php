<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use Config;
use Cookie;
use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\RememberMe\RememberTokenRepositoryInterface;
use SharedKernel\Infrastructure\Security\SplitTokenRememberMeService;

final class AdminRememberMeService extends SplitTokenRememberMeService implements AdminRememberMeServiceInterface
{
    private const COOKIE_NAME = 'remember_admin';

    private const AUDIENCE = 'admin';

    public function __construct(
        RememberTokenRepositoryInterface $tokens,
        AdminUserRepositoryInterface $users
    ) {
        Config::load('security', true);
        parent::__construct($tokens, $users, (int)Config::get('security.remember_me.ttl_seconds'), self::AUDIENCE);
    }

    protected function cookieName(): string
    {
        return self::COOKIE_NAME;
    }

    protected function writeCookie(string $name, string $value, int $ttlSeconds): void
    {
        // `use Cookie;` from the global namespace is deliberate; `use Fuel\Core\Cookie;`
        // would resolve to the parent and drop SameSite=Lax (see ADR-014).
        Cookie::set($name, $value, $ttlSeconds, '/', null, true, true);
    }

    protected function readCookie(string $name): ?string
    {
        $value = Cookie::get($name);
        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function clearCookie(string $name): void
    {
        Cookie::delete($name, '/');
    }
}
