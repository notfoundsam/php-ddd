# ADR-001: Redis Client Implementation

**Status:** Accepted
**Date:** 2026-03-20

## Context

The project needs a Redis client for cache operations shared across both FuelPHP (PHP 7.4) and Laravel (PHP 8.1) applications through the SharedKernel domain layer.

Two options were considered:
- **Predis** — pure PHP library, installed via Composer
- **phpredis** — C extension, installed via PECL

## Decision

We chose **phpredis** (the C extension) as the sole Redis client implementation.

### Why phpredis over Predis

- Better performance (C extension vs pure PHP)
- No Composer dependency — installed at the infrastructure level via `pecl install redis`
- Supported on both PHP 7.4 and 8.1
- Laravel defaults to phpredis

### Connection strategy

- **Always use persistent connections** (`pconnect`) — reuses TCP connections across requests within the same PHP-FPM worker process
- The `connectionType` parameter acts as a persistent ID, allowing separate connections for different roles (e.g., `master`, `replica`)
- For future queue workers: handle reconnection at the worker level (e.g., create a fresh client per job via the factory), not in the client itself

### Database separation

- **Database 0** — reserved for framework use (sessions)
- **Database 1** — application cache

### Cluster mode limitation

Redis Cluster mode (including AWS ElastiCache with cluster mode enabled) **only supports database 0**. The `SELECT` command is not available in cluster mode.

This means:
- **Single-instance / non-cluster replication** — database separation works fine
- **Cluster mode in production** — requires separate Redis instances instead of separate databases

## Architecture

The domain layer defines `RedisClientInterface` — framework-agnostic contract for Redis operations. The infrastructure layer contains:

- `PhpRedisClient` — implements the interface using the phpredis extension
- `RedisConfig` — connection configuration (host, port, database, timeout, connection type)
- `RedisClientFactory` — creates read/write client pair from environment variables
- `ReadWriteRedisClient` — routes reads to replica, writes to master

```
Domain/Redis/
  RedisClientInterface.php      # Contract
  Exceptions/
    RedisConnectionException.php

Infrastructure/Redis/
  PhpRedisClient.php            # Implementation
  RedisConfig.php               # Connection config
  RedisClientFactory.php        # Factory
  ReadWriteRedisClient.php      # Read/write routing
```

### Pipeline isn't included

`pipeline()` is intentionally excluded from `RedisClientInterface`. The existing bulk operations (`mget`, `mset`, `del` with arrays) cover common cases. Pipeline couples callers to the underlying driver since the callback receives a driver-specific object. Add it back only when there is a concrete use case that bulk operations cannot satisfy.

## Consequences

- The `php-redis` extension must be installed in all Docker images (`pecl install redis`)
- Both `fuelphp/composer.json` and `laravel/composer.json` should declare `"ext-redis": "*"`
- Predis is no longer used and can be removed from dependencies if present
- Switching to cluster mode in production will require removing database separation and using separate Redis instances per concern
