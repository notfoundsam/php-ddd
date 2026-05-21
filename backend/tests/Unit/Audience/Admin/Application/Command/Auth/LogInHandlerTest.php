<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Admin\Application\Command\Auth;

use Audience\Admin\Application\Command\Auth\LogInCommand;
use Audience\Admin\Application\Command\Auth\LogInHandler;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use Tests\Fixtures\SharedKernel\Security\SpyAdminRememberMeService;
use Tests\Fixtures\SharedKernel\Security\SpyAdminSessionAuthenticator;
use Tests\Fixtures\SharedKernel\Security\SpyAdminUserRepository;
use Tests\Fixtures\SharedKernel\Security\StubAdminPasswordVerifier;

class LogInHandlerTest extends TestCase
{
    public function testSuccessfulLoginCallsSessionAndUpdatesLastLogin(): void
    {
        $user = new AuthenticatedUser('42', 'admin@example.com', ['admin'], UserType::ADMIN);
        $session = new SpyAdminSessionAuthenticator();
        $rememberMe = new SpyAdminRememberMeService();
        $users = new SpyAdminUserRepository();

        $handler = new LogInHandler(new StubAdminPasswordVerifier($user), $session, $rememberMe, $users);

        $handler(new LogInCommand(new EmailAddress('admin@example.com'), new PlaintextPassword('pw'), false));

        $this->assertSame($user, $session->loggedIn);
        $this->assertCount(1, $users->lastLoginUpdates);
        $this->assertNull($rememberMe->rememberedUser, 'remember=false must NOT call rememberUser');
    }

    public function testRememberFlagCallsRememberMe(): void
    {
        $user = new AuthenticatedUser('42', 'admin@example.com', ['admin'], UserType::ADMIN);
        $session = new SpyAdminSessionAuthenticator();
        $rememberMe = new SpyAdminRememberMeService();
        $users = new SpyAdminUserRepository();

        $handler = new LogInHandler(new StubAdminPasswordVerifier($user), $session, $rememberMe, $users);

        $handler(new LogInCommand(new EmailAddress('admin@example.com'), new PlaintextPassword('pw'), true));

        $this->assertSame($user, $rememberMe->rememberedUser);
    }

    public function testInvalidCredentialsThrowAndSkipSession(): void
    {
        $session = new SpyAdminSessionAuthenticator();
        $rememberMe = new SpyAdminRememberMeService();
        $users = new SpyAdminUserRepository();

        $handler = new LogInHandler(new StubAdminPasswordVerifier(null), $session, $rememberMe, $users);

        $this->expectException(InvalidCredentialsException::class);

        try {
            $handler(new LogInCommand(new EmailAddress('admin@example.com'), new PlaintextPassword('wrong'), true));
        } finally {
            $this->assertNull($session->loggedIn);
            $this->assertNull($rememberMe->rememberedUser);
            $this->assertSame([], $users->lastLoginUpdates);
        }
    }
}
