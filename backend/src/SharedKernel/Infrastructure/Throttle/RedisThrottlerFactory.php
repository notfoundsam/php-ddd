<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Domain\Throttle\ThrottleConfig;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;
use SharedKernel\Domain\Throttle\ThrottleInterface;

final class RedisThrottlerFactory implements ThrottleFactoryInterface
{
    private RedisClientInterface $redisClient;

    public function __construct(RedisClientInterface $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function create(ThrottleConfig $config, string $name): ThrottleInterface
    {
        return new RedisThrottler($this->redisClient, $config, $name);
    }
}
