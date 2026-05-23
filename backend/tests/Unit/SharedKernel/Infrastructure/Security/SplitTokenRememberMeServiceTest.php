<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Security;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserType;
use Tests\Fixtures\SharedKernel\Security\DummyRememberMeService;
use Tests\Fixtures\SharedKernel\Security\InMemoryRememberTokenRepository;
use Tests\Fixtures\SharedKernel\Security\InMemoryUserRepository;

class SplitTokenRememberMeServiceTest extends TestCase
{
    private const COOKIE = 'remember_test';

    private const AUDIENCE = 'admin';

    private InMemoryRememberTokenRepository $tokens;

    private InMemoryUserRepository $users;

    private AuthenticatedUser $user;

    private DummyRememberMeService $service;

    protected function setUp(): void
    {
        $this->tokens = new InMemoryRememberTokenRepository();
        $this->users = new InMemoryUserRepository();
        $this->user = new AuthenticatedUser('42', 'admin@example.com', ['admin'], UserType::ADMIN);
        $this->users->addUser($this->user, '$2y$04$dummy');

        $this->service = new DummyRememberMeService(
            $this->tokens,
            $this->users,
            3600,
            self::AUDIENCE,
            self::COOKIE
        );
    }

    public function testRememberUserPersistsTokenAndWritesCookie(): void
    {
        $this->service->rememberUser($this->user);

        $this->assertCount(1, $this->tokens->rows);
        $this->assertNotNull($this->service->cookies[self::COOKIE] ?? null);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{24}:[a-f0-9]{64}$/', $this->service->cookies[self::COOKIE]);
    }

    public function testTryReanimateReturnsUserAndRotatesValidator(): void
    {
        $this->service->rememberUser($this->user);
        $cookieBefore = $this->service->cookies[self::COOKIE];
        $selector = explode(':', $cookieBefore)[0];
        $hashBefore = $this->tokens->rows[$selector]['validator_hash'];

        $reanimated = $this->service->tryReanimate();

        $this->assertNotNull($reanimated);
        $this->assertSame('42', $reanimated->getId());
        $cookieAfter = $this->service->cookies[self::COOKIE];
        $this->assertNotSame($cookieBefore, $cookieAfter);
        $this->assertNotSame($hashBefore, $this->tokens->rows[$selector]['validator_hash']);
    }

    public function testTryReanimateReturnsNullWithoutCookie(): void
    {
        $this->assertNull($this->service->tryReanimate());
    }

    public function testTryReanimateReturnsNullForUnknownSelector(): void
    {
        $this->service->cookies[self::COOKIE] = str_repeat('a', 24) . ':' . str_repeat('b', 64);

        $this->assertNull($this->service->tryReanimate());
        $this->assertArrayNotHasKey(self::COOKIE, $this->service->cookies);
    }

    public function testTryReanimateRejectsTokenFromDifferentAudience(): void
    {
        // Plant a valid token row tagged with a different audience, then point our cookie at it.
        $foreignService = new DummyRememberMeService(
            $this->tokens,
            $this->users,
            3600,
            'partner',
            'remember_partner'
        );
        $foreignService->rememberUser($this->user);
        $foreignCookie = $foreignService->cookies['remember_partner'];
        [$foreignSelector] = explode(':', $foreignCookie);

        $this->service->cookies[self::COOKIE] = $foreignCookie;

        $this->assertNull(
            $this->service->tryReanimate(),
            'Token tagged for another audience must not authenticate this audience'
        );
        $this->assertArrayHasKey(
            $foreignSelector,
            $this->tokens->rows,
            'Cross-audience reject must NOT delete the other audience row'
        );
        $this->assertArrayNotHasKey(self::COOKIE, $this->service->cookies);
    }

    public function testTryReanimateReturnsNullForValidatorMismatch(): void
    {
        $this->service->rememberUser($this->user);
        $cookie = $this->service->cookies[self::COOKIE];
        $selector = explode(':', $cookie)[0];
        // Tamper: replace validator with something that won't hash to the stored value.
        $this->service->cookies[self::COOKIE] = $selector . ':' . str_repeat('f', 64);

        $this->assertNull($this->service->tryReanimate());
        $this->assertArrayNotHasKey($selector, $this->tokens->rows, 'Mismatched validator must invalidate the row');
    }

    public function testTryReanimateReturnsNullForExpiredToken(): void
    {
        $this->service->rememberUser($this->user);
        $cookie = $this->service->cookies[self::COOKIE];
        $selector = explode(':', $cookie)[0];
        $this->tokens->rows[$selector]['expires_at'] = new DateTimeImmutable('2000-01-01');

        $this->assertNull($this->service->tryReanimate());
        $this->assertArrayNotHasKey($selector, $this->tokens->rows);
    }

    public function testForgetDeletesRowAndClearsCookie(): void
    {
        $this->service->rememberUser($this->user);
        $selector = explode(':', $this->service->cookies[self::COOKIE])[0];

        $this->service->forget();

        $this->assertArrayNotHasKey($selector, $this->tokens->rows);
        $this->assertArrayNotHasKey(self::COOKIE, $this->service->cookies);
    }

    public function testForgetAllForUserDeletesAllAudienceTokensForUser(): void
    {
        $this->service->rememberUser($this->user);
        $this->service->rememberUser($this->user);
        $this->assertCount(2, $this->tokens->rows);

        $this->service->forgetAllForUser($this->user);

        $this->assertCount(0, $this->tokens->rows);
    }

    public function testValidatorIsStoredHashedNotPlain(): void
    {
        $this->service->rememberUser($this->user);
        $cookie = $this->service->cookies[self::COOKIE];
        [$selector, $validator] = explode(':', $cookie);
        $storedHash = $this->tokens->rows[$selector]['validator_hash'];

        $this->assertNotSame($validator, $storedHash);
        $this->assertSame(hash('sha256', $validator), $storedHash);
    }
}
