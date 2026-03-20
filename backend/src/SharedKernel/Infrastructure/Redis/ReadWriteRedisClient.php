<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Redis;

use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Domain\Redis\Exceptions\RedisConnectionException;

/**
 * Redis client with read/write separation
 *
 * Routes read operations to replica and write operations to master.
 * Provides automatic failover from replica to master for read operations
 * when the replica is unavailable.
 */
class ReadWriteRedisClient implements RedisClientInterface
{
    private RedisClientInterface $readClient;
    private RedisClientInterface $writeClient;
    private ?LoggerInterface $logger;

    public function __construct(
        RedisClientInterface $readClient,
        RedisClientInterface $writeClient,
        ?LoggerInterface $logger = null
    ) {
        $this->readClient = $readClient;
        $this->writeClient = $writeClient;
        $this->logger = $logger;
    }

    public function get(string $key): ?string
    {
        try {
            return $this->readClient->get($key);
        } catch (RedisConnectionException $e) {
            $this->logFailover('GET', $e);
            return $this->writeClient->get($key);
        }
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        return $this->writeClient->set($key, $value, $ttl);
    }

    public function del($keys): int
    {
        return $this->writeClient->del($keys);
    }

    public function exists(string $key): bool
    {
        try {
            return $this->readClient->exists($key);
        } catch (RedisConnectionException $e) {
            $this->logFailover('EXISTS', $e);
            return $this->writeClient->exists($key);
        }
    }

    public function mget(array $keys): array
    {
        try {
            return $this->readClient->mget($keys);
        } catch (RedisConnectionException $e) {
            $this->logFailover('MGET', $e);
            return $this->writeClient->mget($keys);
        }
    }

    public function mset(array $keyValues): bool
    {
        return $this->writeClient->mset($keyValues);
    }

    public function keys(string $pattern): array
    {
        try {
            return $this->readClient->keys($pattern);
        } catch (RedisConnectionException $e) {
            $this->logFailover('KEYS', $e);
            return $this->writeClient->keys($pattern);
        }
    }

    public function scan(string $pattern, int $cursor = 0, int $count = 100): array
    {
        try {
            return $this->readClient->scan($pattern, $cursor, $count);
        } catch (RedisConnectionException $e) {
            $this->logFailover('SCAN', $e);
            return $this->writeClient->scan($pattern, $cursor, $count);
        }
    }

    public function ping(): bool
    {
        try {
            return $this->writeClient->ping();
        } catch (RedisConnectionException $e) {
            return $this->readClient->ping();
        }
    }

    public function incr(string $key): int
    {
        return $this->writeClient->incr($key);
    }

    public function setnx(string $key, string $value, int $ttl = 0): bool
    {
        return $this->writeClient->setnx($key, $value, $ttl);
    }

    public function getMaster(string $key): ?string
    {
        return $this->writeClient->get($key);
    }

    private function logFailover(string $operation, RedisConnectionException $e): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->warning('Redis replica failover to master', [
            'operation' => $operation,
            'error' => $e->getMessage(),
        ]);
    }
}
