<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\PasswordHasher\PasswordHasherInterface;
use SharedKernel\Domain\Security\PasswordVerifier\PasswordVerifierInterface;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\Security\UserRepository\UserRepositoryInterface;
use SharedKernel\Domain\ValueObjects\EmailAddress;

abstract class LocalPasswordVerifier implements PasswordVerifierInterface
{
    private UserRepositoryInterface $users;

    private PasswordHasherInterface $hasher;

    /** Pre-computed bcrypt for timing equalization on unknown-user branch; see ADR-014. */
    private string $dummyHash;

    public function __construct(UserRepositoryInterface $users, PasswordHasherInterface $hasher)
    {
        $this->users = $users;
        $this->hasher = $hasher;
        $this->dummyHash = $hasher->hash(bin2hex(random_bytes(16)));
    }

    final public function verify(EmailAddress $email, PlaintextPassword $password): ?AuthenticatedUser
    {
        $hash = $this->users->getPasswordHashByEmail($email);

        if ($hash === null) {
            $this->hasher->verify($password->value(), $this->dummyHash);
            return null;
        }

        if (!$this->hasher->verify($password->value(), $hash)) {
            return null;
        }

        return $this->users->findByEmail($email);
    }
}
