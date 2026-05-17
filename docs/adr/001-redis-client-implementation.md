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

`RedisClientInterface` (Domain) and the `ReadWriteRedisClient` decorator (Infrastructure) are framework-agnostic and live in `backend/src/SharedKernel/`. The `ext-redis`-backed implementation and its factory live in the framework's directory, because they depend on a non-portable extension and on the framework's config layer.

```
backend/src/SharedKernel/
  Domain/Redis/
    RedisClientInterface.php          # Contract
    RedisMasterClientInterface.php    # Marker for strong-consistency reads
    Exceptions/RedisConnectionException.php
  Infrastructure/Redis/
    ReadWriteRedisClient.php          # Routes reads to replica, writes to master

fuelphp/fuel/packages/infrastructure/classes/Redis/
  PhpRedisClient.php                  # ext-redis impl (both interfaces)
  RedisConfig.php                     # Connection DTO
  RedisClientFactory.php              # Builds ReadWriteRedisClient
  RedisMasterClientFactory.php        # Builds master-only client (no failover)

fuelphp/fuel/app/config/redis.php     # primary + reader sections, env-overridable
```

Laravel will mirror this layout: `laravel/app/Infrastructure/Redis/*` + a service provider reading `config('database.redis.*')`.

### Configuration source

`RedisClientFactory` reads from the framework's config (`Config::get('redis.*')` on FuelPHP), not from `getenv()` directly. Defaults live in `app/config/redis.php` in code; env variables only override. This makes per-environment overrides composable (FuelPHP's `app/config/{development,test}/redis.php` cascade) and keeps the factory free of env knowledge. Same pattern as `Blade` (`app/classes/blade.php`).

**Do not delete `db.redis.default` from `fuelphp/fuel/app/config/db.php`** — FuelPHP core (`Session_Redis` via `Redis_Db`) consumes it directly with its own array shape; it is not interchangeable with `redis.php`.

### Terminology: primary/reader vs master/replica

Two vocabularies are used deliberately: AWS-facing config uses `primary`/`reader` (matching ElastiCache endpoint names that ops sees in Terraform/CloudFormation), in-code roles use `master`/`replica` (matching Redis topology terminology). The bridge is `redis.primary.connection_type = 'master'`. Keep the AWS terms at the env/config boundary, the Redis terms in code.

### Strong-consistency reads (RedisMasterClientInterface)

`ReadWriteRedisClient` routes reads to the replica — eventually consistent. Consumers that need to read a value written milliseconds earlier (rate limiting being the canonical case) depend instead on `RedisMasterClientInterface extends RedisClientInterface`, bound to a master-only client. `PhpRedisClient` implements both; `ReadWriteRedisClient` only the base.

`RedisMasterClientFactory` reads `redis.primary` only — no reader fallback, no failover decorator. For security-sensitive workloads, fail-fast on an unreachable master is the correct posture. Both clients share the same `pconnect` persistent-id (`'master'`) so they reuse the same TCP socket per FPM worker — no connection doubling.

### Pipeline isn't included

`pipeline()` is intentionally excluded from `RedisClientInterface`. The existing bulk operations (`mget`, `mset`, `del` with arrays) cover common cases. Pipeline couples callers to the underlying driver since the callback receives a driver-specific object. Add it back only when there is a concrete use case that bulk operations cannot satisfy.

## Consequences

- The `php-redis` extension must be installed in all Docker images (`pecl install redis`)
- Both `fuelphp/composer.json` and `laravel/composer.json` should declare `"ext-redis": "*"`
- Predis is no longer used and can be removed from dependencies if present
- Switching to cluster mode in production will require removing database separation and using separate Redis instances per concern
