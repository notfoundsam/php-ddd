<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\SessionAuthenticator;

use SharedKernel\Domain\Security\AuthenticatedUser;

interface SessionAuthenticatorInterface
{
    /**
     * Marks the current session as authenticated as the given user.
     * Implementations MUST regenerate the session ID to prevent session fixation.
     */
    public function login(AuthenticatedUser $user): void;

    /**
     * Clears the authenticated user from the current session.
     * Implementations MUST regenerate the session ID.
     */
    public function logout(): void;

    /**
     * Returns the user ID stored in the current session, or null if anonymous.
     */
    public function getCurrentUserId(): ?string;
}
