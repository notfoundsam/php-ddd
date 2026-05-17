<?php

declare(strict_types=1);

namespace Infrastructure\Redis;

use Redis;
use RedisException;
use SharedKernel\Domain\Redis\Exceptions\RedisConnectionException;
use SharedKernel\Domain\Redis\RedisClientInterface;

class PhpRedisClient implements RedisClientInterface
{
    private Redis $client;

    public function __construct(RedisConfig $config)
    {
        $this->client = new Redis();

        try {
            $this->client->pconnect(
                $config->getHost(),
                $config->getPort(),
                $config->getTimeout(),
                $config->getConnectionType()
            );
            $this->client->select($config->getDatabase());
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('CONNECT', $e->getMessage());
        }
    }

    public function get(string $key): ?string
    {
        try {
            $value = $this->client->get($key);
            return $value !== false ? (string) $value : null;
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('GET', $e->getMessage());
        }
    }

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        try {
            if ($ttl > 0) {
                return $this->client->setex($key, $ttl, $value);
            }
            return $this->client->set($key, $value);
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('SET', $e->getMessage());
        }
    }

    public function del($keys): int
    {
        try {
            if (is_string($keys)) {
                $keys = [$keys];
            }
            return $this->client->del($keys);
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('DEL', $e->getMessage());
        }
    }

    public function exists(string $key): bool
    {
        try {
            return $this->client->exists($key) > 0;
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('EXISTS', $e->getMessage());
        }
    }

    public function mget(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        try {
            $values = $this->client->mget($keys);
            return array_map(function ($value) {
                return $value !== false ? (string) $value : null;
            }, $values);
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('MGET', $e->getMessage());
        }
    }

    public function mset(array $keyValues): bool
    {
        if (empty($keyValues)) {
            return true;
        }

        try {
            return $this->client->mset($keyValues);
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('MSET', $e->getMessage());
        }
    }

    public function keys(string $pattern): array
    {
        try {
            return $this->client->keys($pattern);
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('KEYS', $e->getMessage());
        }
    }

    public function scan(string $pattern, int $cursor = 0, int $count = 100): array
    {
        try {
            // phpredis modifies $cursor by reference to return the next cursor position
            $ref = $cursor;
            $keys = $this->client->scan($ref, $pattern, $count);

            return [
                'cursor' => $ref,
                'keys' => $keys !== false ? $keys : [],
            ];
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('SCAN', $e->getMessage());
        }
    }

    public function ping(): bool
    {
        try {
            $response = $this->client->ping();
            return $response === true || $response === '+PONG';
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('PING', $e->getMessage());
        }
    }

    public function incr(string $key): int
    {
        try {
            return $this->client->incr($key);
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('INCR', $e->getMessage());
        }
    }

    public function expire(string $key, int $ttl): bool
    {
        try {
            return $this->client->expire($key, $ttl);
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('EXPIRE', $e->getMessage());
        }
    }

    public function setnx(string $key, string $value, int $ttl = 0): bool
    {
        try {
            if ($ttl > 0) {
                // Atomic SET with NX and EX options
                // Returns false/null when key already exists
                return $this->client->set($key, $value, ['NX', 'EX' => $ttl]) === true;
            }
            return $this->client->setnx($key, $value);
        } catch (RedisException $e) {
            throw RedisConnectionException::operationFailed('SETNX', $e->getMessage());
        }
    }

    public function getMaster(string $key): ?string
    {
        return $this->get($key);
    }
}
