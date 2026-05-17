<?php

declare(strict_types=1);

namespace Infrastructure\Redis;

use Fuel\Core\Config;
use RuntimeException;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Infrastructure\Redis\ReadWriteRedisClient;

class RedisClientFactory
{
    public function __invoke(): RedisClientInterface
    {
        Config::load('redis', true);

        $writeClient = $this->buildClient(Config::get('redis.primary', []), 'primary');
        $readClient = $this->buildReader(Config::get('redis.reader', []), $writeClient);

        return new ReadWriteRedisClient($readClient, $writeClient);
    }

    /**
     * Build a client from a config section, throwing if the host is missing.
     *
     * @param array<string,mixed> $cfg
     */
    private function buildClient(array $cfg, string $section): RedisClientInterface
    {
        if (empty($cfg['host'])) {
            throw new RuntimeException(sprintf('redis.%s.host is not configured', $section));
        }

        return new PhpRedisClient(new RedisConfig(
            (string) $cfg['host'],
            (int) ($cfg['port'] ?? 6379),
            (int) ($cfg['database'] ?? 0),
            (float) ($cfg['timeout'] ?? 5.0),
            (string) ($cfg['connection_type'] ?? 'default')
        ));
    }

    /**
     * Reader falls back to the primary client when no reader host is configured.
     *
     * @param array<string,mixed> $cfg
     */
    private function buildReader(array $cfg, RedisClientInterface $writeClient): RedisClientInterface
    {
        if (empty($cfg['host'])) {
            return $writeClient;
        }

        return $this->buildClient($cfg, 'reader');
    }
}
