<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\UserRepository;

use DateTimeImmutable;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\ValueObjects\EmailAddress;

interface UserRepositoryInterface
{
    public function findByEmail(EmailAddress $email): ?AuthenticatedUser;

    public function findById(string $id): ?AuthenticatedUser;

    /**
     * Returns the stored password hash for the given email, or null if no user exists.
     * Separate method so {@see AuthenticatedUser} never carries the hash.
     */
    public function getPasswordHashByEmail(EmailAddress $email): ?string;

    public function updateLastLogin(string $id, DateTimeImmutable $at): void;
}
