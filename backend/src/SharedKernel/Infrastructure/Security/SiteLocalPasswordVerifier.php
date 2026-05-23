<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\PasswordHasher\PasswordHasherInterface;
use SharedKernel\Domain\Security\PasswordVerifier\SitePasswordVerifierInterface;
use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;

final class SiteLocalPasswordVerifier extends LocalPasswordVerifier implements SitePasswordVerifierInterface
{
    public function __construct(SiteUserRepositoryInterface $users, PasswordHasherInterface $hasher)
    {
        parent::__construct($users, $hasher);
    }
}
