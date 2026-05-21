<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\PasswordHasher\PasswordHasherInterface;

final class SpyingHasher implements PasswordHasherInterface
{
    private PasswordHasherInterface $inner;

    public int $verifyCalls = 0;

    public ?string $lastVerifyHash = null;

    public function __construct(PasswordHasherInterface $inner)
    {
        $this->inner = $inner;
    }

    public function hash(string $plaintext): string
    {
        return $this->inner->hash($plaintext);
    }

    public function verify(string $plaintext, string $hash): bool
    {
        $this->verifyCalls++;
        $this->lastVerifyHash = $hash;
        return $this->inner->verify($plaintext, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return $this->inner->needsRehash($hash);
    }
}
