# ADR-002: Cache Module Design

**Status:** Accepted
**Date:** 2026-03-20

## Context

The project needs a caching layer on top of the Redis driver (see ADR-001) that can be shared across both FuelPHP and Laravel applications through the SharedKernel.

PSR-16 (Simple Cache) was considered as the interface contract but was not adopted fully due to unnecessary complexity for our use case.

## Decision

### String-only values

`CacheInterface` stores and returns strings only (`set(string $key, string $value)`, `get(): ?string`). No serialization/deserialization is handled by the cache layer — callers are responsible for encoding (e.g., `json_encode`/`json_decode`).

This avoids implicit serialization behavior and makes the cache contract explicit about what it stores.

### No default parameter on get

`get(string $key): ?string` returns `null` on cache miss. Callers use the `??` operator for defaults:

```php
$value = $cache->get('key') ?? 'fallback';
```

This simplifies the interface and all implementations without losing functionality.

### Integer TTL only

`set(string $key, string $value, int $ttl = 0)` uses `int` seconds instead of `null|int|DateInterval`. `0` means no expiry. This removes `normalizeTtl()` conversion logic from every implementation.

### Single exception class

`CacheException` is the only cache exception, extending `RuntimeException`. `InvalidCacheArgumentException` extends `InvalidArgumentException` separately for validation errors (bad keys, negative TTL). These are two distinct hierarchies:

- `CacheException` — infrastructure failures (connection errors), should be caught and handled
- `InvalidCacheArgumentException` — programmer errors, should be fixed in code

### Namespace-based versioning via decorator

`VersionedCacheDecorator` wraps any `CacheInterface` to add automatic key versioning based on namespace (the part before the first colon in a key). This enables cache invalidation by bumping a version number without flushing the entire cache.

```
Original key:  "user:123"
Versioned key: "core:v1.2.0:user:123"
```

Each bounded context can configure its own versions independently.

### Read/write separation handled at Redis layer

The cache module does not implement read/write separation. `RedisCache` receives a `RedisClientInterface` which may be a `ReadWriteRedisClient` that transparently routes reads to replica and writes to master. The cache layer doesn't need to know about this.

## Architecture

```
Domain/Cache/
  CacheInterface.php              # String-based cache contract
  VersionedCacheInterface.php     # Adds namespace version queries
  CacheException.php              # Infrastructure failure exception
  InvalidCacheArgumentException.php  # Validation exception

Infrastructure/Cache/
  RedisCache.php                  # Redis-backed implementation
  InMemoryCache.php               # In-memory implementation (testing)
  VersionedCacheDecorator.php     # Adds versioning to any CacheInterface
  CacheKeyTransformer.php         # Key validation and transformation
  CacheFactory.php                # Creates RedisCache from DI
```

## Consequences

- Callers must serialize/deserialize values themselves (typically `json_encode`/`json_decode`)
- `clear()` uses `scan()` instead of `keys()` to avoid blocking Redis on large keyspaces
- `delete()` returns `true` even if the key doesn't exist (consistent with PSR-16 semantics)
- Versioned cache invalidation requires a code deployment to bump version numbers
- `InMemoryCache` can be used as a drop-in replacement for testing without Redis
