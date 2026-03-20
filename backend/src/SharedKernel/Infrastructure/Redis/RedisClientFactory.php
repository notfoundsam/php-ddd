<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Redis;

use RuntimeException;
use SharedKernel\Domain\Redis\RedisClientInterface;

class RedisClientFactory
{
    public function __invoke(): RedisClientInterface
    {
        $readClient = $this->createReplica();
        $writeClient = $this->createMaster();

        return new ReadWriteRedisClient($readClient, $writeClient);
    }

    /**
     * Create Redis primary client for write operations
     */
    private function createMaster(): RedisClientInterface
    {
        $host = getenv('REDIS_PRIMARY_ENDPOINT');

        if ($host === false || $host === '') {
            throw new RuntimeException('REDIS_PRIMARY_ENDPOINT environment variable is not set');
        }

        $config = new RedisConfig(
            $host,
            (int) (getenv('REDIS_PRIMARY_PORT') ?: 6379),
            1,
            5.0,
            'master'
        );

        return new PhpRedisClient($config);
    }

    /**
     * Create Redis replica client for read operations
     * Falls back to master if replica endpoints are not configured
     */
    private function createReplica(): RedisClientInterface
    {
        $replicaHost = getenv('REDIS_READER_ENDPOINT');
        $replicaPort = (int) (getenv('REDIS_READER_PORT') ?: 6379);

        // If the replica endpoint is not configured, fall back to master
        if (empty($replicaHost)) {
            return $this->createMaster();
        }

        $config = new RedisConfig(
            $replicaHost,
            $replicaPort,
            1,
            5.0,
            'replica'
        );

        return new PhpRedisClient($config);
    }
}
