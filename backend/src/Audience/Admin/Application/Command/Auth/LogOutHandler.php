<?php

declare(strict_types=1);

namespace Audience\Admin\Application\Command\Auth;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\AdminSessionAuthenticatorInterface;

final class LogOutHandler
{
    private AdminSessionAuthenticatorInterface $session;

    private AdminRememberMeServiceInterface $rememberMe;

    public function __construct(
        AdminSessionAuthenticatorInterface $session,
        AdminRememberMeServiceInterface $rememberMe
    ) {
        $this->session = $session;
        $this->rememberMe = $rememberMe;
    }

    public function __invoke(CommandInterface $command): void
    {
        if (!$command instanceof LogOutCommand) {
            return;
        }
        $this->session->logout();
        $this->rememberMe->forget();
    }
}
