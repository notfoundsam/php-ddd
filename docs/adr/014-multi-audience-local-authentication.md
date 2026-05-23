# ADR-014: Multi-Audience Local Authentication

**Status:** Accepted
**Date:** 2026-05-19

## Context

ADR-008 established RBAC over the CQRS bus but deferred how identity actually gets into the session and how multiple audiences (admin / partner / site) coexist when FuelPHP's session model stores one set of auth keys. ADR-004 already solved cross-framework session reading; ADR-008 already solved authorization. What remains is **authentication** — credential verification, session establishment, persistent login, and the data model behind them.

The project serves admin (≈10s, invite-only), partner (≈100s, custom onboarding), and site/customer (up to ~400k, self-service) populations on three subdomains (`admin.*`, `partner.*`, root). Routing admin through AWS Cognito was considered and rejected at current threat model and team size; the integration overhead (dev stub, env-conditional wiring, SDK error paths) outweighs the benefit. The architecture must leave a clean seam so a Cognito-backed verifier can swap in later without touching commands, handlers, controllers, or the security context.

Login is also never the right module to migrate first under strangler-fig. Since ADR-004 lets Laravel read FuelPHP sessions, login can stay in FuelPHP until the last FuelPHP page is decommissioned, then port in one focused PR. So audience-layer code (commands, handlers, VOs) must not couple to FuelPHP — only infrastructure adapters swap at migration.

## Decision

### Local bcrypt for all three audiences

Locally-stored bcrypt hashes in MySQL via `password_hash` / `password_verify`. The Cognito option remains reachable through the `PasswordVerifierInterface` seam if compliance, MFA, or SSO requirements appear later.

### Three user tables, three role tables, one remember-tokens table

```
admin_users     (id, email UNIQUE, password, email_verified_at, last_login_at, created_at, updated_at)
partner_users   (same shape)
customer_users  (same shape)

admin_user_roles    (admin_user_id, role, granted_at)    PRIMARY KEY (admin_user_id, role)
partner_user_roles  (FK to partner_users)
customer_user_roles (FK to customer_users)

remember_tokens (selector PK, audience VARCHAR(16), user_id, validator_hash, expires_at, created_at, last_used_at)
```

**Three user tables, not a polymorphic `users` with `type` discriminator.** Different audiences have different lifecycles, business fields (added later — `partner_users.company_name`), and access patterns. A polymorphic table conflates indexes and makes per-audience FKs ambiguous (`orders.customer_user_id`). Email uniqueness is per-table.

**Three role tables for real `ON DELETE CASCADE` FKs.** A single polymorphic `user_roles(user_type, user_id, role)` cannot enforce FKs; integrity becomes application-only.

**One `remember_tokens` table** because the logic is genuinely identical across audiences and the token belongs to the **portal**, not the actor. The audience discriminator avoids triplicating the GC cron. `user_id` is polymorphic; the application deletes tokens when users are deleted (no FK).

**Column is `password`, not `password_hash`** — matches Laravel's `Authenticatable` default.

### Composite-key role table (multiple roles per user)

`(user_id, role)` PK — one user has many roles. Required by ADR-008's permission-only model: a manager needing invoice access gets an additional `staff_invoice` role, not a new `manager_with_invoices` role. The resolver loads all roles via JOIN; `AuthorizationService` walks them with union semantics.

### Audience-scoped marker interfaces over named DI bindings

Per-audience seams that actually diverge — `PasswordVerifierInterface`, `RememberMeServiceInterface`, `UserRepositoryInterface` — use empty marker subinterfaces extending a generic base. Handlers depend on the typed marker; DI resolves through interface-to-class binding with no string keys. Common logic lives in an abstract parent; concrete classes are two-line wrappers fixing the audience name (table name, cookie name for remember-me, etc.).

Chosen against PHP-DI's `name`-parameterized bindings, which move resolution into stringly-typed factory closures — refactor-unsafe, opaque to static analysis, and unable to catch "admin handler wired with partner verifier" at compile time.

`SessionAuthenticatorInterface` does **not** follow this pattern — there's nothing to discriminate. See "One session per host" below.

### One session per host, isolated by hostname

A single default session — cookie name `phpddd`, driver `redis`, configured once in `fuel/app/config/session.php` — serves every audience. FuelPHP cookies have no `Domain` attribute, so the browser stores a separate blob per host (`admin.php-ddd.test`, `partner.php-ddd.test`, root). Subdomain dispatch (ADR-013) keeps each portal on its own host, so cross-audience cookie reuse is impossible at the browser level. `FuelPhpSessionAuthenticator` is therefore a single `final` class that calls `Session::instance()` (no arguments) and inherits the cookie name from config.

Per-audience cookie names (`phpddd_admin` / `phpddd_partner` / `phpddd_site`) and three subclasses were prototyped first, then dropped: distinct names defended only against a future misconfiguration that set `Domain=.php-ddd.test`, while paying for that defense with a parallel default `phpddd` session forged on every request by FuelPHP's boot path (double Redis SET+EXPIRE per request) and a latent footgun where a bare `Session::set('key', 'value')` in app code writes to the wrong blob.

`SessionAuthenticator` writes **both** `user_id` and `user_type` on login. Each per-audience resolver compares the stored `user_type` against its own constant (`UserType::ADMIN/PARTNER/CUSTOMER`) before calling `findById` against its repository, returning `null` on mismatch. Independent PK sequences across `admin_users` / `partner_users` / `customer_users` mean an unguarded `findById` could silently resolve a foreign id (admin `id=42` while the session holds partner `id=42`) if host scope ever breaks. With the guard, host-scope is **defense-in-depth**: a future `Domain=.php-ddd.test` misconfig now requires *also* spoofing `user_type`, which is set only by `SessionAuthenticator::login` after credential verification through the audience-specific verifier.

FuelPHP's SimpleAuth was still rejected on a separate ground: it stores `username` / `user_id` / `login_hash` under unprefixed session keys with no notion of audience — a **state-shape** problem (flat top-level keys) independent of which cookie carries the blob. The `user_type` discriminator above is the structural fix; rotating the session ID on `login` / `logout` covers fixation.

### Remember-me via split-token with rotation

OWASP-recommended **selector + validator** split:

1. On `remember=true` login: generate 12-byte hex `selector` and 32-byte hex `validator`. Store `selector`, `sha256(validator)`, `user_id`, `audience`, `expires_at`. Send cookie `selector:validator`.
2. On reanimation: parse cookie, `SELECT WHERE selector = ?` (O(1)), `hash_equals(sha256(validator), validator_hash)` constant-time, rotate validator.
3. On logout: `DELETE` row, clear cookie.

The validator is never stored in plaintext, so a database leak yields no usable cookies. Rotation on each use bounds a stolen cookie's window.

Laravel's built-in single-column `remember_token` was rejected: it cannot support multi-device login, and DB leakage yields direct-use cookies.

### Input validation in the controller; VOs in the command

Controllers run FuelPHP `Validation` on raw POST (format, length, presence, `match_field` for password confirmation). On success, the controller constructs `EmailAddress` / `PlaintextPassword` VOs and passes them into the command. Commands carry **only typed VOs**.

This keeps the throttle decorator (outermost) from burning on malformed input, and keeps the bus surface clean — one command per business intent, not one per HTTP form shape. `PlaintextPassword` redacts in `__toString` / `__debugInfo` / `json_encode` so credentials cannot leak through logging.

The `Command::fromHttpInput()` shape used by audience queries (ADR-011) is **not** applied here — login forms have form-specific concerns (cross-field password confirmation, view-bound validation errors) that are awkward on the command itself.

### Cookie attributes via global `Cookie::set` override

`Cookie::set` / `Cookie::get` live **only** inside the FuelPHP-side `RememberMeService`. Handlers and resolvers call semantic methods (`rememberUser`, `tryReanimate`, `forget`).

**Security flags (Secure, HttpOnly, SameSite=Lax) are not per-service.** A single app-level override at `fuelphp/fuel/app/classes/cookie.php` adds them. The FuelPHP session driver delegates to `\Cookie::set` under the hood, so the session cookie inherits them automatically — no `cookie_secure` / `cookie_samesite` in `config/session.php`. Code writing cookies must `use Cookie;` from the global namespace, where the override lives; `use Fuel\Core\Cookie` resolves to the parent and bypasses the override.

### Decorator chain specifics for login

`LogInCommand` flows through the standard chain (ADR-008). Specifics:

- **Throttle:** registered in `ThrottleConfigDefaults` with a strict 10-attempts-per-10-minutes envelope for both `'anonymous'` (IP-keyed) and `'authenticated'` (user-id-keyed). The authenticated branch is required because per-userType defaults would let admin (`null` = unthrottled) hammer the endpoint after stealing a low-privilege session.
- **Security permission is `null`** — explicit public per ADR-008.
- **CSRF tokens on `POST /login` and `POST /logout`.** `Security::check_token()` runs at the top of each; failure re-renders the form with a generic "session expired" message (login) or redirects to `/login` (logout). FuelPHP's `csrf_autoload` is intentionally not enabled — autoload throws before the controller and escapes the generic-error UX. Logout is POST-only; the GET path 404s so `<img src=...logout>` cannot drive a user off their session.
- **Logout clears `SecurityContext`** alongside `session->logout()` and `rememberMe->forget()`, keeping in-memory and cookie state symmetric.

### Username-enumeration defense + 72-byte password cap

`PasswordVerifier::verify` always calls `password_verify`, even on unknown user, against a dummy bcrypt hash computed in `__construct` with the injected hasher (so cost matches the real path). This equalizes "user exists, wrong password" vs "user does not exist" response time. The dummy is computed eagerly rather than lazily to keep timing constant across every request a worker serves.

`PlaintextPassword` rejects input > 72 bytes (`strlen`, not `mb_strlen` — bcrypt truncates at the byte level). Without the cap, a user could register `"password" + "A"` at byte 73 and later log in with `"password" + "B"` — both hash identically. The HTTP layer lifts the same check to a FuelPHP `max_length:72` validation rule; the VO remains the last line of defense.

## Consequences

### Positive

- Audience-layer code is framework-agnostic — at Laravel migration, only infrastructure adapters swap.
- Marker-interface DI is fully type-driven (no string keys, no factory closures). Refactor-safe and statically analyzable.
- Per-audience schema evolution is decoupled — adding `partner_users.company_name` doesn't touch admin or customer.
- Split-token remember-me is multi-device friendly with no DB-leakage path to usable cookies.
- Login throttle ships with the existing decorator and a config-only change.

### Trade-offs

- High file count, low per-file LOC. The single-class-with-named-bindings alternative was rejected on type-safety grounds.
- `remember_tokens.user_id` has no FK (polymorphic across three user tables). Application code must delete tokens when a user is deleted. Trigger-based enforcement adds DB-side complexity for a path that runs a few times per day.
- Pre-existing per-audience interfaces (`UserResolverInterface`, `SecurityConfigInterface`) still use the older one-interface-many-implementations pattern. Aligning them to marker interfaces is a separate refactor.

### Deferred Decisions

- **Signup flow** for customers and partners, including email verification.
- **Password reset via email** with one-time tokens (same split-token discipline as remember-me).
- **"Logout from all devices"** — `session_version` column + resolver check.
- **Per-audience session timeout** — admin 30min, customer 30d. Trivial via independent `expiration_time` per `Session::forge`.
- **Password rehash on login** when bcrypt cost changes — `needsRehash()` + UPDATE on the hot path. Defer until first cost-factor upgrade.
- **Audit log of auth events** — out-of-scope per ADR-008; revisit on regulatory pressure.
- **Email-keyed throttle on login** as a second layer beside the IP throttle. Useful against credential-stuffing botnets. Requires extending `ThrottleLogicTrait` with a per-command key seam. Carries an account-lockout DoS trade-off — balance via lenient per-email limits and/or CAPTCHA. Defer until production telemetry justifies.
- **Multi-tab rotation race** — two tabs presenting the same cookie cause one to fail validator-equality after the other rotates. Real but rare; mitigation is a grace period or rotation-threshold.

### Future Work

- Cognito-backed admin verifier swapped in via DI, no changes upstream.
- Social login (Google / Apple / Facebook) via OAuth2. New `oauth_identities` table linking provider identity to a local user; login becomes "verify callback → look up local user → `SessionAuthenticator::login()`". Same `AuthenticatedUser`; new controllers, no changes to the auth core.
- Laravel-side adapters bound to the existing marker interfaces.
