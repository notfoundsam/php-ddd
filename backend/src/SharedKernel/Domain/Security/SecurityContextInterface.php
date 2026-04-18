<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

interface SecurityContextInterface
{
    public function getCurrentUser(): ?AuthenticatedUser;

    public function setCurrentUser(AuthenticatedUser $user): void;

    public function hasAuthenticatedUser(): bool;

    public function clearUser(): void;
}
