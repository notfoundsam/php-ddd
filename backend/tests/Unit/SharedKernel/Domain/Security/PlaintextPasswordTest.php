<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Security;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\PlaintextPassword;

class PlaintextPasswordTest extends TestCase
{
    public function testValueGetterReturnsOriginalString(): void
    {
        $password = new PlaintextPassword('s3cr3t!');

        $this->assertSame('s3cr3t!', $password->value());
    }

    public function testEmptyStringRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PlaintextPassword('');
    }

    public function testPasswordExceeding72BytesRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('72 bytes');

        new PlaintextPassword(str_repeat('a', 73));
    }

    public function testPasswordExactly72BytesAccepted(): void
    {
        $password = new PlaintextPassword(str_repeat('a', 72));

        $this->assertSame(str_repeat('a', 72), $password->value());
    }

    public function testToStringIsRedacted(): void
    {
        $password = new PlaintextPassword('s3cr3t!');

        $this->assertSame('[REDACTED]', (string)$password);
    }

    public function testDebugInfoIsRedacted(): void
    {
        $password = new PlaintextPassword('s3cr3t!');

        $info = $password->__debugInfo();

        $this->assertSame(['value' => '[REDACTED]'], $info);
    }

    public function testJsonEncodeDoesNotLeakPlaintext(): void
    {
        $password = new PlaintextPassword('s3cr3t!');

        // PHP's json_encode on an object without JsonSerializable iterates public properties;
        // PlaintextPassword has only a private $value, so the encoded form must be `{}`.
        // This guarantees the password cannot leak through ad-hoc command serialization.
        $this->assertSame('{}', json_encode($password));
    }
}
