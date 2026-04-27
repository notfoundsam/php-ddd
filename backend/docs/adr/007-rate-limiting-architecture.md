# ADR-007: Rate Limiting Architecture

**Status:** Accepted
**Date:** 2026-04-18

## Context

The system needs rate limiting at two levels: HTTP-level flood protection for anonymous traffic, and CQRS-level throttling for authenticated users and specific public endpoints. The CQRS throttle must support different limits per user type (admin, partner, customer), per-command/query overrides, progressive penalties for repeat offenders, and fail-open behavior when the storage backend is unavailable. The implementation must be framework-agnostic (PHP 7.4 compatible in `backend/src/`) for the CQRS layer, integrate with the existing CQRS decorator chain (ADR-006), and support the ongoing FuelPHP-to-Laravel migration.

Key requirements:
- HTTP layer: broad IP-based throttle for anonymous users, framework-specific
- CQRS layer: rate limit authenticated users by user type + ID, with per-command overrides for both authenticated and anonymous
- Different default limits per user type — admins exempt
- Progressive penalties that escalate block duration on repeated violations
- Fail-open on storage driver failure — never block legitimate traffic due to infrastructure issues
- No coupling between commands/queries and throttle configuration
- Driver-agnostic design — Redis is the initial driver, but the throttle layer must not leak Redis-specific concerns

## Decision

### Two-Tier Rate Limiting: Global Defaults + Per-Command Overrides

The throttle system operates at two layers:

**HTTP layer** (framework-specific) — Broad IP-based throttle for anonymous users. Catches floods, scrapers, and malformed requests before they reach the CQRS bus. Authenticated users skip this layer.

**CQRS layer** (framework-agnostic) — Two complementary tiers:

1. **Global defaults per user type** — A shared counter across all commands that caps total throughput per authenticated user. A customer making 50 requests across any combination of commands shares one counter with one limit (e.g., 30 warning / 50 block per minute). This prevents aggregate abuse without requiring per-command configuration.

2. **Per-command overrides for sensitive endpoints** — Commands that are expensive, disruptive, or abuse-prone (e.g., contact forms, bulk imports, payment endpoints) get their own counter with tighter limits. These apply to both authenticated and anonymous users. For anonymous users, only per-command overrides apply at the CQRS level — broad anonymous protection is the HTTP layer's responsibility.

The majority of commands use global defaults with zero configuration. Per-command overrides are added only when a specific command proves it needs protection. This follows the standard API rate-limiting pattern used by Stripe, GitHub, and AWS API Gateway: a global budget with per-endpoint overrides, with independent counters per tier.

### Config-Driven, Not Interface-Driven

Throttle configuration is centralized in a config class (`ThrottleConfigDefaults`) rather than declared on individual commands/queries via a marker interface. A `ThrottleAwareInterface` was the initial implementation but was replaced because:
- It scattered throttle policy across the codebase — each command declared its own limits
- Commands carried identity resolution logic that belongs in infrastructure
- Warning/block callbacks coupled commands to throttle mechanics — the decorator already handles events and exceptions
- Adding or changing throttle rules required modifying command classes, not a single config
- The project already uses centralized config for security permissions (Security.md) — throttle should follow the same pattern

The centralized approach uses a `ThrottleConfigResolverInterface` with a single `resolve()` method. The resolver holds the full config array and resolves the appropriate `ThrottleConfig` at runtime based on the command class and user type.

### Layered Config Resolution

The resolver follows a 6-step fallback chain:

1. **Excluded?** — Command is in the exclusion list → skip throttle
2. **Exact user type match** — Per-command config has a key matching the user type (e.g., `'partner'`) → use it
3. **Authenticated catch-all** — Per-command config has an `'authenticated'` key and user is authenticated → use it
4. **Flat config** — Per-command config has `warning_limit` at top level (no user type split) → use it for all users
5. **User type default** — Global defaults for this user type → use it (`null` means exempt)
6. **No match** → skip throttle

The `'authenticated'` catch-all (step 3) exists because per-command configs often need only two tiers: authenticated vs anonymous. Without it, every per-command split config would need to repeat entries for `'partner'`, `'customer'`, etc. Exact user type keys (step 2) take priority over the catch-all, allowing specific overrides when needed.

### Identity Resolution via SecurityContext and RequestContext

The throttle decorator resolves the caller's identity automatically:
- **Authenticated users** — identified by `$userType . ':' . $user->getId()` (e.g., `customer:42`, `partner:7`), resolved via `SecurityContextInterface`
- **Anonymous users** — identified by `'ip:' . $clientIp`, resolved via `RequestContextInterface`

Identity resolution was initially on the command, but this was moved to the decorator because:
- The command shouldn't know about the HTTP request or session context
- `SecurityContextInterface` is populated by the framework before the CQRS chain runs — the decorator can read it directly
- `RequestContextInterface` provides the IP address — a framework-level concern that doesn't belong on a domain command

`SecurityContextInterface` and `RequestContextInterface` are interface-only in the domain/application layer. Concrete implementations live in the framework directories (FuelPHP reads from `Input::ip()`, Laravel from `$request->ip()`).

### User Types, Not Roles

Throttle limits are keyed by user type (`UserType::ADMIN`, `PARTNER`, `CUSTOMER`, `ANONYMOUS`), not by role. User type represents which authentication provider/table the user comes from — it's a fixed identity characteristic, not a permission grant. One user has exactly one type. `AuthenticatedUser` was extended with a `$type` field (defaulting to `UserType::CUSTOMER` for backward compatibility) to carry this information through the security context.

Roles were considered but rejected because:
- A user with multiple roles (`['user', 'manager']`) would need conflict resolution — which role's limits apply?
- Roles are about authorization (what you can do), not identity (who you are)
- The admin panel, partner portal, and customer portal are separate authentication contexts with separate tables — user type naturally maps to this

`UserType::ANONYMOUS` is a config-only concept — it represents the absence of an authenticated user. It's included in `UserType` constants so the config key space is consistent and validatable.

Background event workers (ADR-005) process events through their own dispatch path and never enter the CQRS bus, so they bypass the throttle decorator entirely. No worker-specific user type is required.

### Decorator Chain Order

The decorator chain order (outermost → innermost) is:

```
ThrottleDecorator → LoggerDecorator → TransactionDecorator → Handler
```

(SecurityDecorator will be inserted between Throttle and Logger when the security module is implemented.)

- **Throttle outermost** — reject abusers before any work. `SecurityContextInterface` is populated by the framework middleware before the CQRS chain, not by the security decorator, so the throttle decorator has access to user identity.
- **Logger outside transaction** — captures the full execution time including transaction commit/rollback. If the logger were inside the transaction, slow commits would be invisible in logs.
- **Transaction innermost** — wraps only the handler's DB work and domain event collection.

### Anonymous Protection Split: HTTP + CQRS

The HTTP layer (described in the two-tier section above) checks authentication status before throttling: if `Auth::check()` is true, the request passes through without IP-based throttle. This prevents blocking legitimate authenticated users behind shared IPs (corporate NAT, VPN). Implemented as a framework-specific controller base class (FuelPHP: abstract controller with `before()` override, Laravel: middleware).

At the CQRS level, there is no anonymous global default — broad anonymous protection is the HTTP layer's responsibility. Per-command anonymous overrides can still be configured for specific public endpoints (e.g., contact forms: 5/hour, registration: 10/hour).

A single-layer approach (all anonymous throttling at CQRS) was the initial design but was replaced because:
- Malformed requests, invalid routes, and controller-level validation failures never reach the CQRS bus — they bypass the throttle entirely
- Tight IP limits at the CQRS level would block authenticated users behind shared IPs
- The HTTP layer is the natural place for IP-based flood protection — it sees all requests regardless of routing outcome

### Fail-Open with Exception Layering

When the storage driver is unavailable, the throttle allows the request through rather than blocking it. This is a deliberate trade-off — temporary loss of rate limiting is preferable to a total service outage.

The fail-open implementation uses two layers of exception handling:

1. **`ThrottleDriverException`** — A driver-agnostic exception thrown by `RedisThrottler` when Redis is unavailable. The decorator catches this and logs a warning.
2. **`ThrottleException`** — Created before event firing and thrown after the try-catch block. This ensures a throttle block decision is never swallowed by a driver failure in the event-firing path.

The initial implementation caught `RedisConnectionException` directly in the decorator trait, coupling the CQRS layer to Redis. This was replaced with `ThrottleDriverException` so the decorator only knows about throttle abstractions. Each driver implementation wraps its connection-specific exceptions into `ThrottleDriverException`.

### Progressive Penalties via Violation Counter

When a user exceeds the block limit, the block duration escalates based on their violation count:
- 1st violation: 5 minutes
- 2nd violation: 1 hour
- 3rd+ violation: 3 hours (caps at the last penalty tier)

The violation counter has its own TTL (recovery period). If the user stops offending for the recovery period, the violation counter resets and the next offense starts at the first penalty tier.

The Redis implementation uses `INCR` + `EXPIRE` (set TTL on first increment) rather than `SETNX` + `INCR`. The `SETNX`/`INCR` pattern has a theoretical race condition: if the key expires between `SETNX` failing and `INCR` executing, `INCR` creates a new key without a TTL, leaving it in Redis forever. The `INCR` + `EXPIRE` pattern avoids this.

### Shared Default Counter vs Per-Command Counters

To implement the two-tier model, default commands share a single `__default__` counter per user, while per-command overrides get their own counter scoped by command short name. This prevents key explosion in Redis (thousands of per-command keys per user) and ensures the global default actually caps total throughput rather than per-command throughput.

The `ThrottleConfigResolver` returns a `ThrottleResolveResult` value object that carries both the `ThrottleConfig` and the scope name (`__default__` for defaults, command short name for overrides). The trait passes the scope to the throttle factory, which uses it as the Redis key namespace.

### Redis Key Structure

All throttle state is stored in Redis with the key pattern:

```
throttle:{scope}:{identifier}:window:{windowId}   — request counter per time window
throttle:{scope}:{identifier}:violations           — progressive penalty counter
throttle:{scope}:{identifier}:blocked              — block timestamp
```

Where `{scope}` is `__default__` for commands using default limits, or the command short name for per-command overrides.

Examples:
```
throttle:__default__:customer:42:window:28456789         — shared counter for all default commands
throttle:CreateOrderCommand:customer:42:window:28456789  — per-command override (authenticated)
throttle:ContactFormCommand:ip:192.168.1.50:window:...   — per-command override (anonymous)
```

The user type prefix in the identifier (`customer:42` vs `partner:42`) ensures users from different tables with overlapping IDs have separate counters. The scope uses the short class name (e.g., `CreateOrderCommand`) rather than the FQCN to keep Redis keys readable. If two bounded contexts have commands with the same short name, they share a counter — this is accepted as rare and arguably correct (same user, same action concept). All Redis operations go to the master node (write client) for strong consistency — reading counters from a replica could undercount due to replication lag.

### Security: Generic Exception Messages

`ThrottleException` uses a generic client-facing message: `"Too many requests. Please try again later."` The blocked identifier (e.g., `customer:42` or `ip:192.168.1.50`) is available only via `getIdentifier()` for server-side logging, and `getRetryAfter()` provides the seconds until the block expires for `Retry-After` headers.

## Components

| Component | Layer | Purpose |
|---|---|---|
| `ThrottleInterface` | Domain | Contract: `attempt()` and `clear()` |
| `ThrottleConfig` | Domain | Value object: limits, window, penalties, recovery period |
| `ThrottleResult` | Domain | Value object: allowed/warning/blocked with retry-after |
| `ThrottleResolveResult` | Domain | Value object: resolved config + scope (`__default__` or command name) |
| `ThrottleFactoryInterface` | Domain | Factory contract: `create(ThrottleConfig, string): ThrottleInterface` |
| `ThrottleException` | Domain | Rate limit exceeded — generic message, identifier for logging |
| `ThrottleDriverException` | Domain | Driver-agnostic connection failure |
| `ThrottleConfigException` | Domain | Invalid configuration values |
| `CqrsThrottleWarningEvent` | Domain | Async event: warning threshold crossed |
| `CqrsThrottleBlockedEvent` | Domain | Async event: request blocked |
| `UserType` | Domain | Constants: ADMIN, PARTNER, CUSTOMER, ANONYMOUS |
| `SecurityContextInterface` | Domain | Request-scoped user identity |
| `ThrottleConfigResolverInterface` | Application | Contract: resolve config by command class and user type |
| `RequestContextInterface` | Application | Client IP address |
| `RedisThrottler` | Infrastructure | Redis sliding-window implementation |
| `RedisThrottlerFactory` | Infrastructure | Creates `RedisThrottler` instances |
| `ThrottleConfigResolver` | Infrastructure | 6-step config resolution with fallback chain |
| `ThrottleConfigDefaults` | Infrastructure | Default throttle limits per user type |
| `ThrottleLogicTrait` | Infrastructure | Shared decorator logic: identity resolution, throttle check, event firing |
| `CommandThrottleDecorator` | Infrastructure | `CommandBusInterface` decorator |
| `QueryThrottleDecorator` | Infrastructure | `QueryBusInterface` decorator |

## Consequences

### Positive
- Two-layer approach catches abuse at the right level — HTTP for raw floods, CQRS for application-level abuse
- Authenticated users skip IP-based throttle — no false blocks behind shared IPs
- Centralized config keeps CQRS throttle policy in one reviewable place — no per-command interface pollution
- Layered resolution provides granularity (per-command, per-user-type) with sensible defaults
- User type-based exemption handles admin panels and background workers without exclusion lists
- Driver-agnostic exceptions allow swapping Redis for another storage backend without changing the decorator
- Fail-open with proper exception layering ensures blocks are enforced even when event firing fails
- Generic exception messages prevent information leakage
- Progressive penalties discourage repeat offenders while allowing recovery

### Trade-offs
- Anonymous protection is split across two layers — requires coordinating HTTP and CQRS throttle configs
- Config resolution has multiple fallback steps — debugging "which config was applied?" requires understanding the chain
- `UserType::ANONYMOUS` is not a real user type but is included in constants for config key consistency
- New commands get throttled by defaults automatically — no opt-in required, which could surprise developers adding internal commands (mitigation: use the `excluded` list)
- `ThrottleConfigDefaults` is a static config class, not a file-based config — changing defaults requires a code deploy, not a config file change
- HTTP layer throttle is framework-specific — each framework (FuelPHP, Laravel) needs its own implementation
