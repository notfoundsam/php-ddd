<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\PasswordVerifier\AdminPasswordVerifierInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\PasswordHasher\PasswordHasherInterface;

final class AdminLocalPasswordVerifier extends LocalPasswordVerifier implements AdminPasswordVerifierInterface
{
    public function __construct(AdminUserRepositoryInterface $users, PasswordHasherInterface $hasher)
    {
        parent::__construct($users, $hasher);
    }
}
