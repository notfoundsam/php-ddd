<?php

declare(strict_types=1);

namespace Audience\Site\Application\Command\Auth;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Domain\Security\RememberMe\SiteRememberMeServiceInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SiteSessionAuthenticatorInterface;

final class LogOutHandler
{
    private SiteSessionAuthenticatorInterface $session;

    private SiteRememberMeServiceInterface $rememberMe;

    private SecurityContextInterface $securityContext;

    public function __construct(
        SiteSessionAuthenticatorInterface $session,
        SiteRememberMeServiceInterface $rememberMe,
        SecurityContextInterface $securityContext
    ) {
        $this->session = $session;
        $this->rememberMe = $rememberMe;
        $this->securityContext = $securityContext;
    }

    public function __invoke(CommandInterface $command): void
    {
        if (!$command instanceof LogOutCommand) {
            return;
        }
        $this->session->logout();
        $this->rememberMe->forget();
        $this->securityContext->clearUser();
    }
}
