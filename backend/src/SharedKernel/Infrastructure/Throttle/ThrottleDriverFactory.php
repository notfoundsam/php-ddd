<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;

final class ThrottleDriverFactory
{
    private Environment $environment;
    private RedisClientInterface $redisClient;

    public function __construct(Environment $environment, RedisClientInterface $redisClient)
    {
        $this->environment = $environment;
        $this->redisClient = $redisClient;
    }

    public function __invoke(): ThrottleFactoryInterface
    {
        if ($this->environment->isProduction() || $this->environment->isStaging()) {
            return new RedisThrottlerFactory($this->redisClient);
        }

        return new NullThrottlerFactory();
    }
}
