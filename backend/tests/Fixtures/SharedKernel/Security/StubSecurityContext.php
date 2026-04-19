<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\SecurityContextInterface;

final class StubSecurityContext implements SecurityContextInterface
{
    private ?AuthenticatedUser $user;

    public function __construct(?AuthenticatedUser $user = null)
    {
        $this->user = $user;
    }

    public function getCurrentUser(): ?AuthenticatedUser
    {
        return $this->user;
    }

    public function setCurrentUser(AuthenticatedUser $user): void
    {
        $this->user = $user;
    }

    public function hasAuthenticatedUser(): bool
    {
        return $this->user !== null;
    }

    public function clearUser(): void
    {
        $this->user = null;
    }
}
