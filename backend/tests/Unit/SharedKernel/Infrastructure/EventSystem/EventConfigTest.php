<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\EventSystem;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Environment;
use SharedKernel\Infrastructure\EventSystem\AsyncEventConfig;
use SharedKernel\Infrastructure\EventSystem\OutboxEventConfig;

class EventConfigTest extends TestCase
{
    public function testOutboxRetryIntervalReturnsConfiguredValues(): void
    {
        $this->assertSame(20, OutboxEventConfig::getRetryInterval(1));
        $this->assertSame(120, OutboxEventConfig::getRetryInterval(2));
        $this->assertSame(900, OutboxEventConfig::getRetryInterval(5));
    }

    public function testOutboxRetryIntervalFallsBackForUnknownCount(): void
    {
        $this->assertSame(900, OutboxEventConfig::getRetryInterval(99));
    }

    public function testAsyncVisibilityTimeoutReturnsConfiguredValues(): void
    {
        $this->assertSame(20, AsyncEventConfig::getVisibilityTimeout(1));
        $this->assertSame(120, AsyncEventConfig::getVisibilityTimeout(2));
        $this->assertSame(900, AsyncEventConfig::getVisibilityTimeout(4));
    }

    public function testAsyncVisibilityTimeoutFallsBackToMaxForUnknownCount(): void
    {
        $this->assertSame(900, AsyncEventConfig::getVisibilityTimeout(99));
    }

    public function testAsyncLongPollWaitSecondsDevelopment(): void
    {
        $env = new Environment('development');

        $this->assertSame(5, AsyncEventConfig::getLongPollWaitSeconds($env));
    }

    public function testAsyncLongPollWaitSecondsProduction(): void
    {
        $env = new Environment('production');

        $this->assertSame(20, AsyncEventConfig::getLongPollWaitSeconds($env));
    }

    public function testAsyncLongPollWaitSecondsDefaultsToProductionForUnknownEnv(): void
    {
        $env = new Environment('staging');

        $this->assertSame(20, AsyncEventConfig::getLongPollWaitSeconds($env));
    }
}
