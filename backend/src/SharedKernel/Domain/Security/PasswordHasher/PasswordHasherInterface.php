<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\PasswordHasher;

interface PasswordHasherInterface
{
    public function hash(string $plaintext): string;

    public function verify(string $plaintext, string $hash): bool;

    public function needsRehash(string $hash): bool;
}
