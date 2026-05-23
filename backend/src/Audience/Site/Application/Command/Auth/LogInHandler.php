<?php

declare(strict_types=1);

namespace Audience\Site\Application\Command\Auth;

use DateTimeImmutable;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;
use SharedKernel\Domain\Security\PasswordVerifier\SitePasswordVerifierInterface;
use SharedKernel\Domain\Security\RememberMe\SiteRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SiteSessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;

final class LogInHandler
{
    private SitePasswordVerifierInterface $verifier;

    private SiteSessionAuthenticatorInterface $session;

    private SiteRememberMeServiceInterface $rememberMe;

    private SiteUserRepositoryInterface $users;

    public function __construct(
        SitePasswordVerifierInterface $verifier,
        SiteSessionAuthenticatorInterface $session,
        SiteRememberMeServiceInterface $rememberMe,
        SiteUserRepositoryInterface $users
    ) {
        $this->verifier = $verifier;
        $this->session = $session;
        $this->rememberMe = $rememberMe;
        $this->users = $users;
    }

    public function __invoke(LogInCommand $command): void
    {
        $user = $this->verifier->verify($command->getEmail(), $command->getPassword());
        if ($user === null) {
            throw InvalidCredentialsException::create();
        }

        $this->session->login($user);
        $this->users->updateLastLogin($user->getId(), new DateTimeImmutable());

        if (!$command->isRemember()) {
            return;
        }
        $this->rememberMe->rememberUser($user);
    }
}
