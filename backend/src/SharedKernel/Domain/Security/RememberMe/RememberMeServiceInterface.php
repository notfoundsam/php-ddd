<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\RememberMe;

use SharedKernel\Domain\Security\AuthenticatedUser;

interface RememberMeServiceInterface
{
    public function rememberUser(AuthenticatedUser $user): void;

    /**
     * Rotates the validator on success.
     */
    public function tryReanimate(): ?AuthenticatedUser;

    public function forget(): void;

    public function forgetAllForUser(AuthenticatedUser $user): void;
}
