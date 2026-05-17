<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Domain\Redis\RedisMasterClientInterface;
use SharedKernel\Domain\Throttle\ThrottleConfig;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;
use SharedKernel\Domain\Throttle\ThrottleInterface;

final class RedisThrottlerFactory implements ThrottleFactoryInterface
{
    private RedisMasterClientInterface $redisClient;

    public function __construct(RedisMasterClientInterface $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function create(ThrottleConfig $config, string $name): ThrottleInterface
    {
        return new RedisThrottler($this->redisClient, $config, $name);
    }
}
