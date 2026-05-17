<?php

declare(strict_types=1);

namespace Infrastructure\Redis;

use Fuel\Core\Config;
use SharedKernel\Domain\Redis\RedisMasterClientInterface;

/**
 * Builds a master-only Redis client for strong-consistency consumers
 * (e.g. RedisThrottler). Reads from redis.primary only — no reader
 * fallback, no failover decorator. If the master is unreachable, the
 * caller fails fast: for rate limiting that's the correct posture.
 *
 * Shares the same pconnect persistent-id ('master') as the write side
 * of ReadWriteRedisClient, so both ultimately reuse the same TCP socket
 * per FPM worker — no connection doubling.
 */
class RedisMasterClientFactory
{
    public function __invoke(): RedisMasterClientInterface
    {
        Config::load('redis', true);

        return PhpRedisClient::fromConfigSection(
            Config::get('redis.primary', []),
            'primary'
        );
    }
}
