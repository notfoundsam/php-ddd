<?php

declare(strict_types=1);

namespace Infrastructure\Redis;

use Fuel\Core\Config;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Infrastructure\Redis\ReadWriteRedisClient;

class RedisClientFactory
{
    public function __invoke(): RedisClientInterface
    {
        Config::load('redis', true);

        $writeClient = PhpRedisClient::fromConfigSection(
            Config::get('redis.primary', []),
            'primary'
        );

        $readerCfg = Config::get('redis.reader', []);
        $readClient = empty($readerCfg['host'])
            ? $writeClient
            : PhpRedisClient::fromConfigSection($readerCfg, 'reader');

        return new ReadWriteRedisClient($readClient, $writeClient);
    }
}
