<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Security;

use SharedKernel\Domain\Security\PasswordHasher\PasswordHasherInterface;

final class BcryptPasswordHasher implements PasswordHasherInterface
{
    private int $cost;

    public function __construct(int $cost = 12)
    {
        $this->cost = $cost;
    }

    public function hash(string $plaintext): string
    {
        return password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plaintext, string $hash): bool
    {
        if ($plaintext === '' || $hash === '') {
            return false;
        }

        return password_verify($plaintext, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }
}
