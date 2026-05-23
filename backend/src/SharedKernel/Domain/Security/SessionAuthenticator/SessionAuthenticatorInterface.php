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

    /**
     * Returned together with the user id so the resolver can refuse to look a user id up
     * against the wrong audience repository if host isolation ever breaks (see ADR-014).
     */
    public function getCurrentUserType(): ?string;
}
