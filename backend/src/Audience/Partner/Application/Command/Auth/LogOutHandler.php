<?php

declare(strict_types=1);

namespace Audience\Partner\Application\Command\Auth;

use SharedKernel\Domain\Security\RememberMe\PartnerRememberMeServiceInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;

final class LogOutHandler
{
    private SessionAuthenticatorInterface $session;

    private PartnerRememberMeServiceInterface $rememberMe;

    private SecurityContextInterface $securityContext;

    public function __construct(
        SessionAuthenticatorInterface $session,
        PartnerRememberMeServiceInterface $rememberMe,
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
