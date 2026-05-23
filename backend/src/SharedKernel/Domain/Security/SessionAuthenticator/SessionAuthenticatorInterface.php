<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\SessionAuthenticator;

use SharedKernel\Domain\Security\AuthenticatedUser;

interface SessionAuthenticatorInterface
{
    /**
     * Implementations MUST regenerate the session ID to prevent session fixation.
     */
    public function login(AuthenticatedUser $user): void;

    /**
     * Implementations MUST regenerate the session ID.
     */
    public function logout(): void;

    public function getCurrentUserId(): ?string;
}
