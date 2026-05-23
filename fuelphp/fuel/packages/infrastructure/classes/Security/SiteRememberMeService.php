<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use Cookie;
use SharedKernel\Domain\Security\RememberMe\RememberTokenRepositoryInterface;
use SharedKernel\Domain\Security\RememberMe\SiteRememberMeServiceInterface;
use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;
use SharedKernel\Infrastructure\Security\SplitTokenRememberMeService;

final class SiteRememberMeService extends SplitTokenRememberMeService implements SiteRememberMeServiceInterface
{
    private const COOKIE_NAME = 'remember_site';

    private const AUDIENCE = 'site';

    public function __construct(
        RememberTokenRepositoryInterface $tokens,
        SiteUserRepositoryInterface $users,
        int $ttlSeconds
    ) {
        parent::__construct($tokens, $users, $ttlSeconds, self::AUDIENCE);
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
