<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use Fuel\Core\Cookie;
use SharedKernel\Domain\Security\RememberMe\PartnerRememberMeServiceInterface;
use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;
use SharedKernel\Domain\Security\RememberMe\RememberTokenRepositoryInterface;
use SharedKernel\Infrastructure\Security\SplitTokenRememberMeService;

final class PartnerRememberMeService extends SplitTokenRememberMeService implements PartnerRememberMeServiceInterface
{
    private const COOKIE_NAME = 'remember_partner';

    private const AUDIENCE = 'partner';

    public function __construct(
        RememberTokenRepositoryInterface $tokens,
        PartnerUserRepositoryInterface $users,
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
