<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\Security\BcryptPasswordHasher;

class BcryptPasswordHasherTest extends TestCase
{
    private BcryptPasswordHasher $hasher;

    protected function setUp(): void
    {
        // Cost 4 (minimum) — keeps the test suite fast while still exercising bcrypt.
        $this->hasher = new BcryptPasswordHasher(4);
    }

    public function testHashAndVerifyRoundTrip(): void
    {
        $hash = $this->hasher->hash('s3cr3t!');

        $this->assertTrue($this->hasher->verify('s3cr3t!', $hash));
    }

    public function testVerifyFailsForWrongPassword(): void
    {
        $hash = $this->hasher->hash('correct');

        $this->assertFalse($this->hasher->verify('wrong', $hash));
    }

    public function testHashHandlesMultibyteAndLongInput(): void
    {
        $password = 'Пароль🔐' . str_repeat('a', 50);

        $hash = $this->hasher->hash($password);

        $this->assertTrue($this->hasher->verify($password, $hash));
    }

    public function testVerifyReturnsFalseForEmptyPlaintext(): void
    {
        $hash = $this->hasher->hash('whatever');

        $this->assertFalse($this->hasher->verify('', $hash));
    }

    public function testVerifyReturnsFalseForEmptyHash(): void
    {
        $this->assertFalse($this->hasher->verify('whatever', ''));
    }

    public function testVerifyReturnsFalseForMalformedHashWithoutThrowing(): void
    {
        $this->assertFalse($this->hasher->verify('whatever', 'not-a-valid-bcrypt-hash'));
    }

    public function testNeedsRehashReturnsFalseForMatchingCost(): void
    {
        $hash = $this->hasher->hash('whatever');

        $this->assertFalse($this->hasher->needsRehash($hash));
    }

    public function testNeedsRehashReturnsTrueWhenCostChanges(): void
    {
        $hash = $this->hasher->hash('whatever');

        $strongerHasher = new BcryptPasswordHasher(6);

        $this->assertTrue($strongerHasher->needsRehash($hash));
    }
}
