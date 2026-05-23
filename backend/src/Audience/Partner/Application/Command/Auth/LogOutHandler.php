<?php

declare(strict_types=1);

namespace Audience\Partner\Application\Command\Auth;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Domain\Security\RememberMe\PartnerRememberMeServiceInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\PartnerSessionAuthenticatorInterface;

final class LogOutHandler
{
    private PartnerSessionAuthenticatorInterface $session;

    private PartnerRememberMeServiceInterface $rememberMe;

    private SecurityContextInterface $securityContext;

    public function __construct(
        PartnerSessionAuthenticatorInterface $session,
        PartnerRememberMeServiceInterface $rememberMe,
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
