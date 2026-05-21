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

    /**
     * Bcrypt hash used solely for response-time equalization when a user is not found.
     * Computed once at construction with the injected hasher so the dummy's cost always
     * matches the real verify path — otherwise the unknown-user branch leaks via timing.
     * The plaintext is throwaway random bytes; never stored, never compared.
     */
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
