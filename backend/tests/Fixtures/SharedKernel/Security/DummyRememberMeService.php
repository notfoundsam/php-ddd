<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\RememberMe\RememberTokenRepositoryInterface;
use SharedKernel\Domain\Security\UserRepository\UserRepositoryInterface;
use SharedKernel\Infrastructure\Security\SplitTokenRememberMeService;

final class DummyRememberMeService extends SplitTokenRememberMeService
{
    /** @var array<string, string> */
    public array $cookies = [];

    private string $cookieName;

    public function __construct(
        RememberTokenRepositoryInterface $tokens,
        UserRepositoryInterface $users,
        int $ttlSeconds,
        string $audience,
        string $cookieName
    ) {
        parent::__construct($tokens, $users, $ttlSeconds, $audience);
        $this->cookieName = $cookieName;
    }

    protected function cookieName(): string
    {
        return $this->cookieName;
    }

    protected function writeCookie(string $name, string $value, int $ttlSeconds): void
    {
        $this->cookies[$name] = $value;
    }

    protected function readCookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    protected function clearCookie(string $name): void
    {
        unset($this->cookies[$name]);
    }
}
