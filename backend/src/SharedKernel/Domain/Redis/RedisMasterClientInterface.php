<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Redis;

/**
 * Marker interface for clients that talk directly to the Redis master node.
 *
 * Use this type for consumers that need strong-consistency reads — i.e. a
 * value just written by another request must be visible immediately, with
 * no replica lag. The canonical example is rate limiting, where a
 * just-blocked identifier must not slip through via a stale replica.
 *
 * Standard cache reads should keep depending on RedisClientInterface and
 * route through ReadWriteRedisClient.
 */
interface RedisMasterClientInterface extends RedisClientInterface
{
}
