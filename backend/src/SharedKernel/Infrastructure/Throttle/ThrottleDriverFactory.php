<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Redis\RedisMasterClientInterface;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;

final class ThrottleDriverFactory
{
    private Environment $environment;
    private RedisMasterClientInterface $redisClient;

    public function __construct(Environment $environment, RedisMasterClientInterface $redisClient)
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
