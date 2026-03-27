# ADR-004: Shared Session Between FuelPHP and Laravel

**Status:** Accepted
**Date:** 2026-03-27

## Context

The project is migrating from FuelPHP to Laravel incrementally. During migration, both frameworks serve routes on the same domain via a reverse proxy. Users navigate between FuelPHP and Laravel routes within the same browsing session, requiring full session parity — both frameworks must read and write the same session data, including authentication state.

FuelPHP stores sessions in Redis as a PHP-serialized array with three elements: `[$keys, $data, $flash]`, where `$keys` contains metadata (session ID, timestamps, IP hash, user agent), `$data` contains application session variables, and `$flash` contains flash message state. Laravel uses a different serialization format for its native Redis session driver.

Three approaches were considered:

1. **FuelPHP-native session adapter in Laravel** — Laravel reads/writes FuelPHP's exact Redis format
2. **Neutral session format** — both frameworks adopt a new shared format (requires modifying FuelPHP)
3. **Auth token bridge** — share only authentication state, not full session data

## Decision

### FuelPHP-native session adapter in Laravel (Option 1)

A custom Laravel `SessionHandlerInterface` (`FuelPhpSessionHandler`) reads and writes Redis using FuelPHP's serialization format. Laravel application code uses the standard `session()` API — the custom format is hidden behind the handler interface.

This approach was chosen because:
- Full session parity — both frameworks read/write the same Redis key, same format
- The coupling to FuelPHP's format is acceptable since FuelPHP is legacy and won't change
- The adapter is temporary — removed once migration is complete

### Unified session cookie name

Both frameworks use `phpddd` as the session cookie name. FuelPHP configures this in `session.php`, Laravel hardcodes it in `config/session.php` (along with `driver`, `secure`, and other session settings) rather than using `.env` variables, since these values are the same across all environments.

### Cookie value serialization bridge

FuelPHP serializes the cookie value as `serialize([$session_id])`, while Laravel expects a raw session ID string. A `DecodeFuelPhpSessionCookie` middleware in Laravel handles translation in both directions:
- On request: deserializes the FuelPHP cookie to extract the raw session ID
- On response: re-serializes the session ID back to FuelPHP's format

This middleware runs before `EncryptCookies` in the web middleware group.

### Session ID length compatibility

FuelPHP generates 32-character session IDs, while Laravel's `Store::isValidId()` requires exactly 40 characters. A custom `FuelPhpSessionStore` extends Laravel's `Store` to accept both 32-char (FuelPHP) and 40-char (Laravel) session IDs. A custom `FuelPhpSessionManager` replaces Laravel's default `SessionManager` to use this store.

### FuelPHP keys metadata preservation

When Laravel creates a new session or writes back to an existing one, the handler preserves FuelPHP's `$keys` metadata array (`session_id`, `previous_id`, `ip_hash`, `user_agent`, `created`, `updated`, `payload`). If `$keys` is empty (new session originated from Laravel), the handler populates all required fields from the current request to prevent FuelPHP validation errors.

### Authentication bridge via middleware

A `FuelPhpAuthMiddleware` in Laravel reads the user ID from session data (written by FuelPHP's Auth driver) and calls `Auth::login()` to set Laravel's auth context. This runs after `StartSession` in the web middleware group, allowing downstream code to use Laravel's standard `Auth::user()`, policies, and gates.

### Cookie encryption removed, security hardened

FuelPHP's default `encrypt_cookie` for sessions was disabled because encrypting a random session ID adds no security value — the ID is already a cryptographically random token used only as a Redis lookup key. Actual session data remains server-side in Redis.

Instead, cookie security relies on standard HTTP protections:
- **Secure: true** — cookie only transmitted over HTTPS
- **HttpOnly: true** — JavaScript cannot access the cookie (XSS protection)
- **SameSite: Lax** — cookie not sent on cross-site POST requests (CSRF protection)

FuelPHP's core `Cookie` class did not support `SameSite` (uses the old `setcookie()` signature). An app-level `Cookie` class override (`fuel/app/classes/cookie.php`) extends the core class to use PHP 7.3+ array syntax for `setcookie()`, adding `SameSite` support. The `secure` and `samesite` values are applied after the core config merge to prevent FuelPHP's defaults from overriding them.

The `phpddd` cookie is excluded from Laravel's `EncryptCookies` middleware since FuelPHP writes it unencrypted.

### CSRF tokens are independent

FuelPHP stores CSRF tokens in cookies (`fuel_csrf_token`), not in the session. Laravel stores CSRF tokens in the session (`_token` key). The two mechanisms use separate storage and separate cookies, so they do not conflict and require no adapter.

### Session rotation ownership

FuelPHP owns session ID rotation (every 300 seconds by default). The Laravel adapter follows `rotated_session_id` pointers when reading but does not rotate sessions itself. This avoids conflicts where both frameworks attempt rotation independently.

### Redis connection isolation

Laravel uses a dedicated `fuelphp_session` Redis connection with no key prefix and database 0, matching FuelPHP's Redis configuration. This prevents Laravel's default Redis prefix from corrupting session keys.

## Components

| Component | Location | Purpose |
|---|---|---|
| `FuelPhpSessionHandler` | `laravel/app/Session/` | Reads/writes FuelPHP's Redis session format |
| `FuelPhpSessionStore` | `laravel/app/Session/` | Accepts 32-char FuelPHP session IDs |
| `FuelPhpSessionManager` | `laravel/app/Session/` | Uses custom store instead of Laravel's default |
| `FuelPhpSessionServiceProvider` | `laravel/app/Providers/` | Registers custom session manager and driver |
| `DecodeFuelPhpSessionCookie` | `laravel/app/Http/Middleware/` | Translates cookie format between frameworks |
| `FuelPhpAuthMiddleware` | `laravel/app/Http/Middleware/` | Bridges FuelPHP auth state to Laravel's Auth |
| `Cookie` | `fuelphp/fuel/app/classes/` | Adds SameSite support and secure defaults |

## Removal Plan

When FuelPHP is fully decommissioned:

1. Change `SESSION_DRIVER` to `redis` (Laravel native) in `session.php`
2. Update `SESSION_COOKIE` if desired
3. Delete `FuelPhpSessionHandler`, `FuelPhpSessionStore`, `FuelPhpSessionManager`, `FuelPhpSessionServiceProvider`
4. Delete `DecodeFuelPhpSessionCookie` and `FuelPhpAuthMiddleware`
5. Remove `fuelphp_session` Redis connection from `database.php`
6. Remove `phpddd` from `EncryptCookies::$except`
7. Remove `DecodeFuelPhpSessionCookie` and `FuelPhpAuthMiddleware` from `Kernel.php`
8. Delete `fuelphp/fuel/app/classes/cookie.php` and its bootstrap entry
9. Accept one-time session invalidation (users log in again) or run a migration script to convert session format

No application code changes are needed because all Laravel code uses the standard `session()` and `Auth` APIs.
