<?php

declare(strict_types=1);

namespace Audience\Partner\Application\Command\Auth;

use DateTimeImmutable;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;
use SharedKernel\Domain\Security\PasswordVerifier\PartnerPasswordVerifierInterface;
use SharedKernel\Domain\Security\RememberMe\PartnerRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\PartnerSessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;

final class LogInHandler
{
    private PartnerPasswordVerifierInterface $verifier;

    private PartnerSessionAuthenticatorInterface $session;

    private PartnerRememberMeServiceInterface $rememberMe;

    private PartnerUserRepositoryInterface $users;

    public function __construct(
        PartnerPasswordVerifierInterface $verifier,
        PartnerSessionAuthenticatorInterface $session,
        PartnerRememberMeServiceInterface $rememberMe,
        PartnerUserRepositoryInterface $users
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
