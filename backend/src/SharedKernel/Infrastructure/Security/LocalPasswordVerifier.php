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
     * Lazily-computed bcrypt hash used solely for response-time equalization when a
     * user is not found. Computed once on the first unknown-user branch with the
     * injected hasher so the dummy's cost always matches the real verify path —
     * otherwise the unknown-user branch leaks via timing. Kept out of the constructor
     * so resolving the verifier through DI doesn't pay the bcrypt cost on every
     * request that builds the bus (e.g. unrelated pages).
     */
    private ?string $dummyHash = null;

    public function __construct(UserRepositoryInterface $users, PasswordHasherInterface $hasher)
    {
        $this->users = $users;
        $this->hasher = $hasher;
    }

    final public function verify(EmailAddress $email, PlaintextPassword $password): ?AuthenticatedUser
    {
        $hash = $this->users->getPasswordHashByEmail($email);

        if ($hash === null) {
            $this->dummyHash = $this->dummyHash ?? $this->hasher->hash(bin2hex(random_bytes(16)));
            $this->hasher->verify($password->value(), $this->dummyHash);
            return null;
        }

        if (!$this->hasher->verify($password->value(), $hash)) {
            return null;
        }

        return $this->users->findByEmail($email);
    }
}
