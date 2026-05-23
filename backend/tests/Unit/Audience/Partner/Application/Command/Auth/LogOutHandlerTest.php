<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Partner\Application\Command\Auth;

use Audience\Partner\Application\Command\Auth\LogOutCommand;
use Audience\Partner\Application\Command\Auth\LogOutHandler;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserType;
use Tests\Fixtures\SharedKernel\Security\SpyPartnerRememberMeService;
use Tests\Fixtures\SharedKernel\Security\SpyPartnerSessionAuthenticator;
use Tests\Fixtures\SharedKernel\Security\StubSecurityContext;

class LogOutHandlerTest extends TestCase
{
    public function testLogOutClearsSessionRememberMeAndSecurityContext(): void
    {
        $user = new AuthenticatedUser('7', 'partner@example.com', ['partner_owner'], UserType::PARTNER);
        $session = new SpyPartnerSessionAuthenticator();
        $session->loggedIn = $user;
        $rememberMe = new SpyPartnerRememberMeService();
        $securityContext = new StubSecurityContext($user);

        $handler = new LogOutHandler($session, $rememberMe, $securityContext);

        $handler(new LogOutCommand());

        $this->assertTrue($session->loggedOut);
        $this->assertTrue($rememberMe->forgotten);
        $this->assertNull($securityContext->getCurrentUser());
    }
}
