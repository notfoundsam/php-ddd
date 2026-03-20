<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Redis;

final class RedisConfig
{
    private string $host;
    private int $port;
    private int $database;
    private float $timeout;
    private string $connectionType;

    public function __construct(
        string $host,
        int $port = 6379,
        int $database = 0,
        float $timeout = 5.0,
        string $connectionType = 'default'
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->timeout = $timeout;
        $this->connectionType = $connectionType;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getDatabase(): int
    {
        return $this->database;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getConnectionType(): string
    {
        return $this->connectionType;
    }
}
