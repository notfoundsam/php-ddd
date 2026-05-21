<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use DateInterval;
use DateTimeImmutable;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\RememberMe\RememberMeServiceInterface;
use SharedKernel\Domain\Security\RememberMe\RememberTokenRepositoryInterface;
use SharedKernel\Domain\Security\UserRepository\UserRepositoryInterface;

abstract class SplitTokenRememberMeService implements RememberMeServiceInterface
{
    /** 12 random bytes → 24 hex chars. */
    private const SELECTOR_BYTES = 12;

    /** 32 random bytes → 64 hex chars. */
    private const VALIDATOR_BYTES = 32;

    private RememberTokenRepositoryInterface $tokens;

    private UserRepositoryInterface $users;

    private int $ttlSeconds;

    private string $audience;

    public function __construct(
        RememberTokenRepositoryInterface $tokens,
        UserRepositoryInterface $users,
        int $ttlSeconds,
        string $audience
    ) {
        $this->tokens = $tokens;
        $this->users = $users;
        $this->ttlSeconds = $ttlSeconds;
        $this->audience = $audience;
    }

    final public function rememberUser(AuthenticatedUser $user): void
    {
        $selector = $this->randomHex(self::SELECTOR_BYTES);
        $validator = $this->randomHex(self::VALIDATOR_BYTES);

        $now = $this->now();
        $expiresAt = $now->add(new DateInterval('PT' . $this->ttlSeconds . 'S'));

        $this->tokens->insert(
            $selector,
            $this->audience,
            $user->getId(),
            $this->hashValidator($validator),
            $expiresAt
        );

        $this->writeCookie($this->cookieName(), $selector . ':' . $validator, $this->ttlSeconds);
    }

    final public function tryReanimate(): ?AuthenticatedUser
    {
        $cookieValue = $this->readCookie($this->cookieName());
        if ($cookieValue === null) {
            return null;
        }

        $parts = explode(':', $cookieValue, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            $this->clearCookie($this->cookieName());
            return null;
        }
        [$selector, $validator] = $parts;

        $row = $this->tokens->findBySelector($selector);
        if ($row === null) {
            $this->clearCookie($this->cookieName());
            return null;
        }

        // Defense in depth: cookies are already per-subdomain-scoped, but reject any selector
        // that resolves to a row tagged for a different audience before honouring it.
        if ($row['audience'] !== $this->audience) {
            $this->clearCookie($this->cookieName());
            return null;
        }

        $now = $this->now();
        if ($row['expires_at'] <= $now) {
            $this->tokens->deleteBySelector($selector);
            $this->clearCookie($this->cookieName());
            return null;
        }

        if (!hash_equals($row['validator_hash'], $this->hashValidator($validator))) {
            // Possible token reuse / theft — invalidate and force re-login.
            $this->tokens->deleteBySelector($selector);
            $this->clearCookie($this->cookieName());
            return null;
        }

        $newValidator = $this->randomHex(self::VALIDATOR_BYTES);
        $this->tokens->rotate($selector, $this->hashValidator($newValidator), $now);
        $remainingTtl = $row['expires_at']->getTimestamp() - $now->getTimestamp();
        if ($remainingTtl < 0) {
            $remainingTtl = 0;
        }
        $this->writeCookie($this->cookieName(), $selector . ':' . $newValidator, $remainingTtl);

        return $this->users->findById($row['user_id']);
    }

    final public function forget(): void
    {
        $cookieValue = $this->readCookie($this->cookieName());
        if ($cookieValue !== null) {
            $parts = explode(':', $cookieValue, 2);
            if (count($parts) === 2 && $parts[0] !== '') {
                $this->tokens->deleteBySelector($parts[0]);
            }
        }
        $this->clearCookie($this->cookieName());
    }

    final public function forgetAllForUser(AuthenticatedUser $user): void
    {
        $this->tokens->deleteByUserAndAudience($user->getId(), $this->audience);
        $this->clearCookie($this->cookieName());
    }

    /**
     * Returns the cookie name this audience instance writes to.
     * Concrete subclasses fix this to a per-audience constant (e.g. 'remember_admin').
     */
    abstract protected function cookieName(): string;

    abstract protected function writeCookie(string $name, string $value, int $ttlSeconds): void;

    abstract protected function readCookie(string $name): ?string;

    abstract protected function clearCookie(string $name): void;

    /**
     * Override in tests if deterministic time is needed.
     */
    protected function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    /**
     * Override in tests if deterministic values are needed.
     */
    protected function randomHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }

    private function hashValidator(string $validator): string
    {
        return hash('sha256', $validator);
    }
}
