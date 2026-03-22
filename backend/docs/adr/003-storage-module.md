# ADR-003: Storage Module Design

**Status:** Accepted
**Date:** 2026-03-22

## Context

The project needs a file storage abstraction that works across both FuelPHP and Laravel applications through the SharedKernel. Storage must support AWS S3 in production/staging and local filesystem in development, with CDN URL resolution for public assets.

Laravel's Filesystem (Flysystem) was considered but not adopted — it would add a heavy dependency to the framework-agnostic domain layer and provides more abstraction than needed for our use case.

## Decision

### Framework-agnostic LocalStorage

`LocalStorage` uses plain PHP functions (`file_get_contents`, `file_put_contents`, `mkdir`, `unlink`) instead of framework-specific file helpers. The initial implementation used `Fuel\Core\File`, which would have required a separate `LaravelLocalStorage` during migration. A single framework-agnostic implementation serves both.

The storage base path is defined as a constant (`/app/storage/`) since both frameworks share the same Docker volume.

### Stream-based interface

`StorageInterface` uses `Psr\Http\Message\StreamInterface` for `put()` and `get()`. This avoids loading entire files into memory and works naturally with S3's streaming API. Convenience methods (`putString`, `getString`, `putFile`) are provided via `StringStorageTrait` for common cases.

### Path traversal protection

All storage implementations validate paths through `buildFullPath()` in `StringStorageTrait`, which rejects any path containing `..` segments. This prevents directory traversal attacks where a caller could access files outside the storage root (e.g., `../../etc/passwd`). The validation applies to both S3 (preventing access to unintended bucket keys) and local storage.

### Exception chaining

`StorageException` factory methods accept an optional `$previous` parameter to chain the original exception. The implementations log the original exception and re-throw a `StorageException` with the cause attached, preserving the full error chain for debugging.

### Behavioral consistency between implementations

`S3Storage::delete()` checks file existence before calling `deleteObject` because S3 silently succeeds when deleting non-existent keys. This ensures both implementations throw `StorageException::fileNotFound()` consistently, matching the interface contract.

`S3Storage::exists()` only returns `false` for 404 responses. Other S3 errors (network failures, permission denied) are re-thrown rather than silently returning `false`.

### CdnUrlResolver decoupled from Environment

`CdnUrlResolver` accepts a `array<string, string> $mappings` (prefix => CDN base URL) via constructor instead of reading environment-specific configuration internally. The DI container provides the correct mappings per environment. This removes the `Environment` dependency and avoids hardcoded domain names.

### Environment variable validation in StorageFactory

`StorageFactory` validates `AWS_BUCKET` and `AWS_DEFAULT_REGION` environment variables before creating `S3Storage`, throwing `InvalidArgumentException` with a clear message if either is missing. Variable names follow Laravel/AWS SDK conventions for consistency across the project.

### S3 CopySource URL encoding

`S3Storage::copy()` URL-encodes each path segment of the source key individually, preserving `/` separators while encoding special characters (spaces, `+`, etc.) as required by the S3 API.

## Architecture

```
Domain/Storage/
  StorageInterface.php        # Stream-based storage contract
  StorageException.php        # Operation failure exceptions with chaining

Infrastructure/Storage/
  LocalStorage.php            # Framework-agnostic local filesystem
  S3Storage.php               # AWS S3 implementation
  StringStorageTrait.php      # putString/getString/putFile + buildFullPath
  StorageFactory.php          # Creates storage based on environment
  S3ClientFactory.php         # Creates S3Client with configurable region
  CdnUrlResolver.php          # Maps storage paths to CDN URLs
```

## Consequences

- Both implementations share path validation and string convenience methods via trait
- `LocalStorage` tests require GuzzleHttp PSR-7 (available via FuelPHP composer, skipped in backend-only test suite)
- `S3Storage` tests require AWS SDK (same situation)
- Adding a new storage backend (e.g., EFS, vendor S3) means implementing `StorageInterface` and updating `StorageFactory`
- CDN mappings must be configured in DI per environment
- `LocalStorage` is tied to `/app/storage/` constant — if the path needs to vary, it should become a constructor parameter
