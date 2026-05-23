<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Admin\Application\Command\Auth;

use Audience\Admin\Application\Command\Auth\LogOutCommand;
use Audience\Admin\Application\Command\Auth\LogOutHandler;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserType;
use Tests\Fixtures\SharedKernel\Security\SpyAdminRememberMeService;
use Tests\Fixtures\SharedKernel\Security\SpySessionAuthenticator;
use Tests\Fixtures\SharedKernel\Security\StubSecurityContext;

class LogOutHandlerTest extends TestCase
{
    public function testLogOutClearsSessionRememberMeAndSecurityContext(): void
    {
        $user = new AuthenticatedUser('42', 'admin@example.com', ['admin'], UserType::ADMIN);
        $session = new SpySessionAuthenticator();
        $session->loggedIn = $user;
        $rememberMe = new SpyAdminRememberMeService();
        $securityContext = new StubSecurityContext($user);

        $handler = new LogOutHandler($session, $rememberMe, $securityContext);

        $handler(new LogOutCommand());

        $this->assertTrue($session->loggedOut);
        $this->assertTrue($rememberMe->forgotten);
        $this->assertNull($securityContext->getCurrentUser());
    }
}
