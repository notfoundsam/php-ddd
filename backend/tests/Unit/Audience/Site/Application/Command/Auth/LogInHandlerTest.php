<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Site\Application\Command\Auth;

use Audience\Site\Application\Command\Auth\LogInCommand;
use Audience\Site\Application\Command\Auth\LogInHandler;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use Tests\Fixtures\SharedKernel\Security\SpySiteRememberMeService;
use Tests\Fixtures\SharedKernel\Security\SpySiteSessionAuthenticator;
use Tests\Fixtures\SharedKernel\Security\SpySiteUserRepository;
use Tests\Fixtures\SharedKernel\Security\StubSitePasswordVerifier;

class LogInHandlerTest extends TestCase
{
    public function testSuccessfulLoginCallsSessionAndUpdatesLastLogin(): void
    {
        $user = new AuthenticatedUser('100', 'c@example.com', [], UserType::CUSTOMER);
        $session = new SpySiteSessionAuthenticator();
        $rememberMe = new SpySiteRememberMeService();
        $users = new SpySiteUserRepository();

        $handler = new LogInHandler(new StubSitePasswordVerifier($user), $session, $rememberMe, $users);

        $handler(new LogInCommand(new EmailAddress('c@example.com'), new PlaintextPassword('pw'), false));

        $this->assertSame($user, $session->loggedIn);
        $this->assertCount(1, $users->lastLoginUpdates);
        $this->assertNull($rememberMe->rememberedUser, 'remember=false must NOT call rememberUser');
    }

    public function testRememberFlagCallsRememberMe(): void
    {
        $user = new AuthenticatedUser('100', 'c@example.com', [], UserType::CUSTOMER);
        $session = new SpySiteSessionAuthenticator();
        $rememberMe = new SpySiteRememberMeService();
        $users = new SpySiteUserRepository();

        $handler = new LogInHandler(new StubSitePasswordVerifier($user), $session, $rememberMe, $users);

        $handler(new LogInCommand(new EmailAddress('c@example.com'), new PlaintextPassword('pw'), true));

        $this->assertSame($user, $rememberMe->rememberedUser);
    }

    public function testInvalidCredentialsThrowAndSkipSession(): void
    {
        $session = new SpySiteSessionAuthenticator();
        $rememberMe = new SpySiteRememberMeService();
        $users = new SpySiteUserRepository();

        $handler = new LogInHandler(new StubSitePasswordVerifier(null), $session, $rememberMe, $users);

        $this->expectException(InvalidCredentialsException::class);

        try {
            $handler(new LogInCommand(new EmailAddress('c@example.com'), new PlaintextPassword('wrong'), true));
        } finally {
            $this->assertNull($session->loggedIn);
            $this->assertNull($rememberMe->rememberedUser);
            $this->assertSame([], $users->lastLoginUpdates);
        }
    }
}
