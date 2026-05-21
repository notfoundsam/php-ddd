<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\PasswordVerifier\PartnerPasswordVerifierInterface;
use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;
use SharedKernel\Domain\Security\PasswordHasher\PasswordHasherInterface;

final class PartnerLocalPasswordVerifier extends LocalPasswordVerifier implements PartnerPasswordVerifierInterface
{
    public function __construct(PartnerUserRepositoryInterface $users, PasswordHasherInterface $hasher)
    {
        parent::__construct($users, $hasher);
    }
}
