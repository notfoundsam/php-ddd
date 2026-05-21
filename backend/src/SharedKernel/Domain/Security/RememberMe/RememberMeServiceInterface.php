<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\RememberMe;

use SharedKernel\Domain\Security\AuthenticatedUser;

interface RememberMeServiceInterface
{
    /**
     * Issues a new remember-me token for the user and persists the cookie.
     */
    public function rememberUser(AuthenticatedUser $user): void;

    /**
     * Attempts to validate the existing remember-me cookie and return the user.
     * Rotates the validator on success.
     * Returns null on any failure (no cookie, parse error, expired, mismatch).
     */
    public function tryReanimate(): ?AuthenticatedUser;

    /**
     * Invalidates the current remember-me token and clears the cookie.
     */
    public function forget(): void;

    /**
     * Invalidates ALL remember-me tokens for the given user across all devices.
     */
    public function forgetAllForUser(AuthenticatedUser $user): void;
}
