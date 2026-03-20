<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Redis;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\Redis\RedisConfig;

class RedisConfigTest extends TestCase
{
    public function testConstructWithAllParameters(): void
    {
        $config = new RedisConfig('redis.example.com', 6380, 2, 10.0, 'custom');

        $this->assertSame('redis.example.com', $config->getHost());
        $this->assertSame(6380, $config->getPort());
        $this->assertSame(2, $config->getDatabase());
        $this->assertSame(10.0, $config->getTimeout());
        $this->assertSame('custom', $config->getConnectionType());
    }

    public function testConstructWithDefaults(): void
    {
        $config = new RedisConfig('localhost');

        $this->assertSame('localhost', $config->getHost());
        $this->assertSame(6379, $config->getPort());
        $this->assertSame(0, $config->getDatabase());
        $this->assertSame(5.0, $config->getTimeout());
        $this->assertSame('default', $config->getConnectionType());
    }
}
