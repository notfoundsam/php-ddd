<?php

declare(strict_types=1);

namespace Audience\Admin\Application\Command\Auth;

use DateTimeImmutable;
use SharedKernel\Domain\Security\PasswordVerifier\AdminPasswordVerifierInterface;
use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\AdminSessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;

final class LogInHandler
{
    private AdminPasswordVerifierInterface $verifier;

    private AdminSessionAuthenticatorInterface $session;

    private AdminRememberMeServiceInterface $rememberMe;

    private AdminUserRepositoryInterface $users;

    public function __construct(
        AdminPasswordVerifierInterface $verifier,
        AdminSessionAuthenticatorInterface $session,
        AdminRememberMeServiceInterface $rememberMe,
        AdminUserRepositoryInterface $users
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
