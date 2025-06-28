<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

interface AuthenticationServiceInterface
{
    public function authenticate(string $username, string $password): AuthenticatedUser;

    public function refresh(): bool;
}
