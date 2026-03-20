<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Cache;

use SharedKernel\Domain\Cache\CacheInterface;
use SharedKernel\Domain\Redis\RedisClientInterface;

final class CacheFactory
{
    private RedisClientInterface $redisClient;

    public function __construct(RedisClientInterface $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function __invoke(): CacheInterface
    {
        return new RedisCache($this->redisClient);
    }
}
