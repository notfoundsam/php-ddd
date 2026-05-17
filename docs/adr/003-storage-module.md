# ADR-003: Storage Module Design

**Status:** Accepted
**Date:** 2026-05-17 (revised — adds public/private convention & per-subdomain CDN)

## Context

The project needs a file-storage abstraction shared between two framework applications (FuelPHP and Laravel) running off the same domain layer. Requirements:

- AWS S3 in cloud environments, local filesystem in development.
- The two PHP containers in dev must read each other's writes — files uploaded from FuelPHP must be visible to Laravel and vice versa, mirroring the S3 model where both apps point at one bucket.
- The local CDN (`images.php-ddd.test`) must serve the same directory in dev.
- Framework-specific configuration (bucket, region, root path) must come through each framework's native config layer (`Config::get(...)` on FuelPHP, `config(...)` on Laravel), not through `getenv()` calls inside framework-neutral code (CLAUDE.md rule, also applied to Redis and Logger).

Flysystem 3.x requires PHP 8.0+. FuelPHP runs on PHP 7.4 for the duration of the migration; Flysystem 1.x and 2.x are EOL. Until the FuelPHP side retires, Flysystem is not adopted — Laravel's eventual side will use `Storage::disk(...)` (Flysystem under the hood) wrapped in `StorageInterface`.

## Decision

### Layer split

`StorageInterface`, `StorageException`, and `CdnUrlResolver` live in `backend/src/SharedKernel/{Domain,Infrastructure}/Storage/`. They have no framework or PSR-7 implementation dependencies and are shared by both apps.

Concrete adapters, the factory, and `StringStorageTrait` live in each framework's directory:

- FuelPHP: `fuelphp/fuel/packages/infrastructure/classes/Storage/` — `LocalStorage`, `S3Storage`, `S3ClientFactory`, `StorageFactory`, `StringStorageTrait` under namespace `Infrastructure\Storage`.
- Laravel (when implemented): a thin wrapper over `Storage::disk(...)` exposing the same `StorageInterface`.

`StringStorageTrait` is placed adjacent to the adapters because it uses `GuzzleHttp\Psr7\Utils` (a PSR-7 *implementation*, not the interface package) for `string ↔ Stream` conversion in `putString`/`putFile`. Implementation-level helpers belong with implementations, not in the framework-agnostic domain layer. Duplication of `LocalStorage`/`S3Storage`/trait between FuelPHP and Laravel is accepted while two PHP versions coexist. The interface contract + identical trait code guarantee path-validation semantics, exception mapping, and S3 behavioural quirks stay symmetric.

### Configuration cascade

`fuelphp/fuel/app/config/storage.php` declares defaults in code (`driver=s3`, root, bucket, region) with `getenv(...)` only as override. Per-env files (`config/development/storage.php`, `config/test/storage.php`) override `driver=local` and the local root. The factory calls `Config::load('storage', true)` and reads `Config::get('storage.*')` — driver selection is not derived from `Environment::isCloudLike()`; it's the config cascade.

`LocalStorage` root is a constructor parameter (`storage.local.root`), not a hard-coded constant. The test environment writes to `/tmp/php-ddd-storage-test` to avoid polluting the shared dev volume.

### Stream-based interface

`StorageInterface::put`/`get` use `Psr\Http\Message\StreamInterface`. This avoids loading entire files into memory and works naturally with S3's streaming API. `psr/http-message` is declared in `backend/composer.json` for the same reason `psr/container` is — it's an interface-only package, not an implementation. `StringStorageTrait` provides `putString`/`getString`/`putFile` convenience methods over the stream API.

### Path-traversal protection

`StringStorageTrait::buildFullPath()` (in `Infrastructure\Storage`) rejects any path containing `..` segments (`(^|/)\.\.(/|$)`). The check applies to both local (file-system traversal) and S3 (unintended bucket keys) and runs on every storage operation.

### Behavioural consistency between adapters

- `S3Storage::delete()` calls `exists()` first because S3 silently succeeds when deleting non-existent keys; `LocalStorage::delete()` throws on missing files. Both adapters surface `StorageException::fileNotFound()` consistently.
- `S3Storage::exists()` returns `false` only on HTTP 404; other S3 errors (network, permission denied) are re-thrown rather than masked.
- `S3Storage::copy()` URL-encodes each path segment of the source key individually, preserving `/` separators while encoding spaces and `+` as the S3 CopySource header requires.
- `StorageException` factory methods accept a `$previous` exception; adapters log the original error and re-throw with the cause chained.

### CDN URL resolution

`CdnUrlResolver` takes `array<string, string> $mappings` (prefix → CDN base URL) via constructor. The DI container provides per-environment mappings. No `Environment` dependency, no hard-coded domains.

### Public/private boundary: `public/<area>/` prefix + per-subdomain CDN

`public/` is a path-level marker, not a separate disk. Anything stored under `public/<area>/...` is intended for world-readable distribution through a CDN; anything else (`users/123/passport.jpg`, internal CSVs) stays reachable only through `StorageInterface`.

Each public area gets its own CDN subdomain whose origin path is `public/<area>/`:

- `public/images/...` is served by `images.php-ddd.test` (dev) → CloudFront distribution mapped to `s3://<bucket>/public/images/` (prod). A future `public/files/...` would get its own `files.php-ddd.test` distribution.
- The subdomain hides the `public/<area>/` prefix from the URL: stored key `public/images/logo.png` becomes `https://images.php-ddd.test/logo.png`.
- `CdnUrlResolver` mirrors this by matching the longest prefix in its mapping (`'public/images/' => CDN_IMAGES_URL`) and stripping it when forming the URL.

This avoids the "one CDN for all public content" trap: each subdomain's scope is fixed at its origin path, so a new area (audio, files, ...) is added by registering a new subdomain + mapping entry, not by widening an existing one.

### Dev environment: shared `./storage/` mount

A top-level `./storage/` directory at the repo root is mounted as `/app/storage` in both `fuelphp` and `laravel` services so files written by either container are visible to the other. The local CDN nginx mounts the same directory as `/usr/share/nginx/html:ro`, and each `server` block in `dev-tools/cdn-nginx.conf` sets `root` to the area-specific subdirectory (e.g. `root /usr/share/nginx/html/public/images` for `images.php-ddd.test`). Files outside `public/<area>/` are unreachable through nginx, so the public/private boundary is enforced by `root`, not by extension whitelist.

Frontend assets remain reachable through the main app nginx at `/build/...` and `/assets/...` — the local CDN is exclusively for user-uploaded content, matching the S3+CloudFront production model.

## Layout

```
backend/src/SharedKernel/
  Domain/Storage/
    StorageInterface.php          # Stream-based contract (PSR-7)
    StorageException.php          # Operation failures with cause chaining
  Infrastructure/Storage/
    CdnUrlResolver.php            # Path prefix → CDN URL mapping

fuelphp/fuel/packages/infrastructure/classes/Storage/
  LocalStorage.php                # PHP-native filesystem, root from config
  S3Storage.php                   # AWS S3 adapter
  S3ClientFactory.php             # Creates Aws\S3\S3Client per region
  StorageFactory.php              # Reads Config::get('storage.*'), picks adapter
  StringStorageTrait.php          # buildFullPath + put/getString/putFile

fuelphp/fuel/app/config/
  storage.php                     # Defaults + getenv overrides
  development/storage.php         # driver=local
  test/storage.php                # driver=local, root=/tmp/...
```

## Consequences

- Both adapters share path validation and string helpers via the trait; the interface contract is enforced uniformly.
- New storage backends (EFS, vendor S3) require an `Infrastructure\Storage` adapter and an additional `driver=...` branch in the factory.
- Adding the Laravel-side implementation is a separate task; the contract is already stable.
- `backend/` no longer references `aws/aws-sdk-php` even from its own source; the dependency stays declared in `fuelphp/composer.json` only.
- Misconfiguration (missing bucket/region/root, unknown driver) fails fast at container boot, not at first I/O.
