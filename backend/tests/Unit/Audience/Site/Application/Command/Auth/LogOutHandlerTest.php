<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Site\Application\Command\Auth;

use Audience\Site\Application\Command\Auth\LogOutCommand;
use Audience\Site\Application\Command\Auth\LogOutHandler;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserType;
use Tests\Fixtures\SharedKernel\Security\SpySiteRememberMeService;
use Tests\Fixtures\SharedKernel\Security\SpySiteSessionAuthenticator;
use Tests\Fixtures\SharedKernel\Security\StubSecurityContext;

class LogOutHandlerTest extends TestCase
{
    public function testLogOutClearsSessionRememberMeAndSecurityContext(): void
    {
        $user = new AuthenticatedUser('100', 'c@example.com', [], UserType::CUSTOMER);
        $session = new SpySiteSessionAuthenticator();
        $session->loggedIn = $user;
        $rememberMe = new SpySiteRememberMeService();
        $securityContext = new StubSecurityContext($user);

        $handler = new LogOutHandler($session, $rememberMe, $securityContext);

        $handler(new LogOutCommand());

        $this->assertTrue($session->loggedOut);
        $this->assertTrue($rememberMe->forgotten);
        $this->assertNull($securityContext->getCurrentUser());
    }
}
