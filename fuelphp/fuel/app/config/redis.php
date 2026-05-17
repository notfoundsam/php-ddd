<?php

declare(strict_types=1);

/**
 * Redis configuration for the application Redis client (RedisClientFactory).
 *
 * Not to be confused with db.redis.default, which is consumed by FuelPHP core
 * (Session_Redis via Redis_Db) and follows Fuel's own array shape.
 *
 * Reader fallback: if REDIS_READER_ENDPOINT is unset, the factory routes
 * reads to the primary endpoint.
 */
return [
    'primary' => [
        'host'            => getenv('REDIS_PRIMARY_ENDPOINT') ?: null,
        'port'            => (int) (getenv('REDIS_PRIMARY_PORT') ?: 6379),
        'database'        => 1,
        'timeout'         => 5.0,
        'connection_type' => 'master',
    ],
    'reader' => [
        'host'            => getenv('REDIS_READER_ENDPOINT') ?: null,
        'port'            => (int) (getenv('REDIS_READER_PORT') ?: 6379),
        'database'        => 1,
        'timeout'         => 5.0,
        'connection_type' => 'replica',
    ],
];
