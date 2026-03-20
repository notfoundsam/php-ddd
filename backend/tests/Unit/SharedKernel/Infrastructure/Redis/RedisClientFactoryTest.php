<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Redis;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SharedKernel\Infrastructure\Redis\RedisClientFactory;

class RedisClientFactoryTest extends TestCase
{
    /** @var string|false */
    private $originalPrimaryAddr;
    /** @var string|false */
    private $originalPrimaryPort;
    /** @var string|false */
    private $originalReaderAddr;
    /** @var string|false */
    private $originalReaderPort;

    protected function setUp(): void
    {
        $this->originalPrimaryAddr = getenv('REDIS_PRIMARY_ENDPOINT');
        $this->originalPrimaryPort = getenv('REDIS_PRIMARY_PORT');
        $this->originalReaderAddr = getenv('REDIS_READER_ENDPOINT');
        $this->originalReaderPort = getenv('REDIS_READER_PORT');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('REDIS_PRIMARY_ENDPOINT', $this->originalPrimaryAddr);
        $this->restoreEnv('REDIS_PRIMARY_PORT', $this->originalPrimaryPort);
        $this->restoreEnv('REDIS_READER_ENDPOINT', $this->originalReaderAddr);
        $this->restoreEnv('REDIS_READER_PORT', $this->originalReaderPort);
    }

    public function testThrowsExceptionWhenPrimaryAddrNotSet(): void
    {
        putenv('REDIS_PRIMARY_ENDPOINT');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('REDIS_PRIMARY_ENDPOINT environment variable is not set');

        $factory = new RedisClientFactory();
        $factory();
    }

    public function testThrowsExceptionWhenPrimaryAddrIsEmpty(): void
    {
        putenv('REDIS_PRIMARY_ENDPOINT=');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('REDIS_PRIMARY_ENDPOINT environment variable is not set');

        $factory = new RedisClientFactory();
        $factory();
    }

    /**
     * @param string|false $value
     */
    private function restoreEnv(string $name, $value): void
    {
        if ($value === false) {
            putenv($name);
        } else {
            putenv("$name=$value");
        }
    }
}
