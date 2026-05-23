<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Partner\Application\Command\Auth;

use Audience\Partner\Application\Command\Auth\LogInCommand;
use Audience\Partner\Application\Command\Auth\LogInHandler;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use Tests\Fixtures\SharedKernel\Security\SpyPartnerRememberMeService;
use Tests\Fixtures\SharedKernel\Security\SpyPartnerSessionAuthenticator;
use Tests\Fixtures\SharedKernel\Security\SpyPartnerUserRepository;
use Tests\Fixtures\SharedKernel\Security\StubPartnerPasswordVerifier;

class LogInHandlerTest extends TestCase
{
    public function testSuccessfulLoginCallsSessionAndUpdatesLastLogin(): void
    {
        $user = new AuthenticatedUser('7', 'partner@example.com', ['partner_owner'], UserType::PARTNER);
        $session = new SpyPartnerSessionAuthenticator();
        $rememberMe = new SpyPartnerRememberMeService();
        $users = new SpyPartnerUserRepository();

        $handler = new LogInHandler(new StubPartnerPasswordVerifier($user), $session, $rememberMe, $users);

        $handler(new LogInCommand(new EmailAddress('partner@example.com'), new PlaintextPassword('pw'), false));

        $this->assertSame($user, $session->loggedIn);
        $this->assertCount(1, $users->lastLoginUpdates);
        $this->assertNull($rememberMe->rememberedUser, 'remember=false must NOT call rememberUser');
    }

    public function testRememberFlagCallsRememberMe(): void
    {
        $user = new AuthenticatedUser('7', 'partner@example.com', ['partner_owner'], UserType::PARTNER);
        $session = new SpyPartnerSessionAuthenticator();
        $rememberMe = new SpyPartnerRememberMeService();
        $users = new SpyPartnerUserRepository();

        $handler = new LogInHandler(new StubPartnerPasswordVerifier($user), $session, $rememberMe, $users);

        $handler(new LogInCommand(new EmailAddress('partner@example.com'), new PlaintextPassword('pw'), true));

        $this->assertSame($user, $rememberMe->rememberedUser);
    }

    public function testInvalidCredentialsThrowAndSkipSession(): void
    {
        $session = new SpyPartnerSessionAuthenticator();
        $rememberMe = new SpyPartnerRememberMeService();
        $users = new SpyPartnerUserRepository();

        $handler = new LogInHandler(new StubPartnerPasswordVerifier(null), $session, $rememberMe, $users);

        $this->expectException(InvalidCredentialsException::class);

        try {
            $handler(new LogInCommand(new EmailAddress('partner@example.com'), new PlaintextPassword('wrong'), true));
        } finally {
            $this->assertNull($session->loggedIn);
            $this->assertNull($rememberMe->rememberedUser);
            $this->assertSame([], $users->lastLoginUpdates);
        }
    }
}
