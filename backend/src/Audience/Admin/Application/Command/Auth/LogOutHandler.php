<?php

declare(strict_types=1);

namespace Audience\Admin\Application\Command\Auth;

use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;

final class LogOutHandler
{
    private SessionAuthenticatorInterface $session;

    private AdminRememberMeServiceInterface $rememberMe;

    private SecurityContextInterface $securityContext;

    public function __construct(
        SessionAuthenticatorInterface $session,
        AdminRememberMeServiceInterface $rememberMe,
        SecurityContextInterface $securityContext
    ) {
        $this->session = $session;
        $this->rememberMe = $rememberMe;
        $this->securityContext = $securityContext;
    }

    public function __invoke(LogOutCommand $command): void
    {
        $this->session->logout();
        $this->rememberMe->forget();
        $this->securityContext->clearUser();
    }
}
