# ADR-014: Multi-Audience Local Authentication

**Status:** Accepted
**Date:** 2026-05-19

## Context

ADR-008 established RBAC over the CQRS bus and the `UserResolverInterface` seam, but deferred two things: how a user's identity actually gets into the session in the first place, and how multiple audiences (admin / partner / site) coexist when FuelPHP's session model stores only one set of auth keys. ADR-004 already solved cross-framework session reading; ADR-008 already solved authorization. What remains is the **authentication** side — credential verification, session establishment, persistent ("remember me") login, and the data model behind them.

The project will eventually serve admin, partner, and site (customer) audiences on three separate subdomains (`admin.*`, `partner.*`, root host). Their populations differ in scale (≈10s of admins, ≈100s of partners, up to ~400k registered customers) and in lifecycle (admin = invite-only, partner = nuanced onboarding with custom business fields, customer = self-service signup). Earlier in design we considered routing the most sensitive audience (admin) through AWS Cognito for password storage outsourcing and built-in protections. That path was rejected for now: the integration overhead (dev environment via `cognito-local` or stub, env-conditional driver wiring, SDK error handling) outweighs benefits at the current threat model and team size. The architecture must, however, leave a clean seam so a Cognito-backed verifier can be added later without touching commands, handlers, controllers, or the security context.

A second consideration: login is **never** the right module to migrate first under a strangler-fig migration. ADR-004 already lets Laravel read FuelPHP sessions, so the login code can stay in FuelPHP until the last FuelPHP page is decommissioned, then port in one focused PR. The design must therefore not couple the audience-layer code (commands, handlers, VOs) to FuelPHP — the migration target is to swap only the infrastructure adapters.

## Decision

### Local password storage with bcrypt for all three audiences

All three audiences use locally-stored bcrypt hashes in MySQL. No Cognito, no Auth0, no third-party identity provider. PHP's built-in `password_hash` / `password_verify` cover the cryptographic surface; everything else (login flow, lockout, throttle, session, remember-me) is thin glue around battle-tested primitives. The OWASP Authentication Cheat Sheet is followed as a checklist, not as inspiration for creative work.

This is the standard model for PHP applications of this scale and threat profile. It is explicitly **not** a stopgap awaiting a "real" identity provider — it is the chosen long-term architecture. The Cognito option remains reachable through the `PasswordVerifierInterface` seam if compliance, MFA, or SSO requirements appear later.

### Three user tables, three role tables, one remember-tokens table

```
admin_users     (id, email UNIQUE, password, email_verified_at, last_login_at, created_at, updated_at)
partner_users   (same shape)
customer_users  (same shape)

admin_user_roles    (admin_user_id, role, granted_at)    PRIMARY KEY (admin_user_id, role)
partner_user_roles  (same shape with FK to partner_users)
customer_user_roles (same shape with FK to customer_users)

remember_tokens (selector PK, audience VARCHAR(16), user_id, validator_hash, expires_at, created_at, last_used_at)
```

**Three separate user tables, not one polymorphic `users` table with `type` discriminator.** Different audiences have different lifecycles, different business fields (added later — `partner_users.company_name`, `customer_users.phone`, etc.), and different access patterns. A polymorphic table forces all three to share a column set forever, conflates indexes, and makes per-audience FKs to other tables (`orders.customer_user_id`) ambiguous. The small schema duplication is the lesser cost.

**Email uniqueness is per-table, not global.** `admin@x.com` and `customer@x.com` may legitimately be the same human in two different roles — distinct accounts, distinct passwords, distinct lifecycles.

**Three role tables, not one polymorphic table.** A single `user_roles(user_type, user_id, role)` table cannot enforce FKs; database integrity becomes application-only. Three tables let each `FOREIGN KEY ... ON DELETE CASCADE` actually work. The duplication is three identical `CREATE TABLE` statements — acceptable.

**One shared `remember_tokens` table with an `audience` discriminator.** The remember-me logic is genuinely identical across audiences (same generate, same lookup, same rotate, same expire). The token is owned by the **portal**, not by the actor type, and there are no FKs (`user_id` is polymorphic by design — the application enforces integrity by deleting tokens when users are deleted). Splitting into three identical tables here would add zero benefit and triplicate the GC cron.

**Column is `password`, not `password_hash`.** Semantically `password_hash` is more precise, but Laravel's `Authenticatable` trait looks for `password` by default and the convention is universally understood as "the hash, never the plaintext." The Laravel-friendly name saves one `getAuthPassword()` override per future Eloquent model.

### Multiple roles per user (composite key)

`admin_user_roles` has composite PK `(admin_user_id, role)` — one user has many roles. This is required by ADR-008's permission-only model: when a manager needs invoice access, the answer is to grant them an additional `staff_invoice` role, not to invent a `manager_with_invoices` role. The resolver loads all roles via JOIN; `AuthorizationService` walks them with union semantics ("any role granting the permission allows the command"). Flat-role models (one role per user) force role explosion exactly where ADR-008 was designed to prevent it.

### One cookie per audience, isolated by hostname

Each audience has its own session cookie name:
- `phpddd_admin` on `admin.php-ddd.test`
- `phpddd_partner` on `partner.php-ddd.test`
- `phpddd` on the root host

Because the audiences live on different subdomains (ADR-013), the browser already isolates these cookies. There is no possibility of an admin session "becoming" a customer session through cookie reuse. Each `Controller_<Audience>_Abstract` resolves its per-audience `*SessionAuthenticator`, which lazily forges its own Redis-backed `Session::instance(<cookie_name>)` with the audience cookie name passed inline to `Session::forge(...)`. The global `app/config/session.php` defines defaults shared across all three; per-audience overrides (different idle timeouts, etc.) would be added by extending the inline config in `FuelPhpSessionAuthenticator::session()`.

The default FuelPHP SimpleAuth driver was considered and rejected: it stores `username` / `user_id` / `login_hash` under unprefixed session keys, conflicting across audiences in a single browser. SimpleAuth's instance system separates **config**, not **session state**. A namespaced fork was considered, but at that point we are writing our own auth code anyway — and SimpleAuth has no Laravel analogue, so the work would be discarded at migration. Writing ~50 lines of audience-aware session wrapping is the smaller bet.

### Remember-me via split-token, separate table, with rotation

Persistent login uses the OWASP-recommended **selector + validator** split:

1. On `remember=true` login: generate a 12-byte hex `selector` and a 32-byte hex `validator`. Store `selector`, `sha256(validator)`, `user_id`, `audience`, `expires_at` in `remember_tokens`. Send the browser one cookie containing `selector:validator`.
2. On reanimation: parse cookie, `SELECT WHERE selector = ?` (indexed, O(1)), `hash_equals(sha256(validator), validator_hash)` in constant time, rotate validator (new random, UPDATE row + reissue cookie).
3. On logout: `DELETE` the row, clear the cookie.

The validator is **never stored in plaintext**. A database leak does not yield usable cookies. The split prevents timing attacks on lookup (the selector is the lookup key; equality is constant-time on the validator). Rotation on each use limits the window of a stolen cookie.

Laravel's built-in remember model — a single `remember_token` column on the user — was considered and rejected. Single-column models cannot support multi-device login (logging in on a second device invalidates the first), and database leakage there yields direct-use cookies. The cost of doing this properly is one custom `EloquentUserProvider` subclass at Laravel migration time (~50 lines).

### Audience-scoped marker interfaces over named DI bindings

Per-audience seams use **marker interfaces** that extend a generic base, not named string keys in the DI container:

```
SessionAuthenticatorInterface
    ├── AdminSessionAuthenticatorInterface
    ├── PartnerSessionAuthenticatorInterface
    └── SiteSessionAuthenticatorInterface
```

The same pattern applies to `PasswordVerifierInterface`, `RememberMeServiceInterface`, and `UserRepositoryInterface`. A handler declares its dependency as a typed marker (`AdminPasswordVerifierInterface`), and the DI container resolves it through interface-to-class binding with no string keys involved. Common logic lives in an abstract parent class; concrete classes are two-line wrappers fixing the audience name.

This was a deliberate choice against PHP-DI's `name`-parameterized bindings. Named bindings move dependency resolution from the type system into stringly-typed factory closures — refactor-unsafe, opaque to static analysis, and they prevent the compiler from catching "admin handler accidentally wired with partner verifier." The marker-interface pattern costs ~5 extra files per audience (one marker interface + one concrete subclass per role: verifier, session, remember, repository) in exchange for full type-driven wiring. The existing project conventions (`UserResolverInterface`, `SecurityConfigInterface`) use plain per-class implementations of a shared interface and rely on factories; that older pattern is not extended here. Migrating those to marker interfaces is left for a separate refactor.

### Input validation in the controller; VOs in the command

Controllers run FuelPHP `Validation` on raw POST input (format, length, presence, `match_field` for password confirmation in signup flows). On success, the controller constructs domain VOs (`EmailAddress`, `PlaintextPassword`) and passes them into the command. Commands carry **only typed VOs**, never raw strings.

Two consequences follow. First, the throttle decorator (outermost in the chain) does not get burned on malformed input — invalid emails never reach the bus. Second, two-field forms (password + password_confirmation at signup) handle their cross-field validation in the controller, before a command is constructed; the command itself carries a single, validated password VO. This keeps the command bus surface clean: one command per business intent, not one command per HTTP form shape.

`PlaintextPassword` overrides `__toString()` and `__debugInfo()` to return `[REDACTED]`. This prevents accidental logging via `json_encode($command)`, `var_dump`, or the command-logger decorator. It is a small defensive habit with a real payoff — credential strings should never appear in any log stream, ever.

The `Command::fromHttpInput()` shape used elsewhere (ADR-011 for audience queries) is **not** used here. Login forms have form-specific concerns (password confirmation, validation rules surfacing to view) that are awkward to express on the command. The controller does the parsing-and-validation work explicitly; the command stays a pure typed DTO.

### Cookie management encapsulated in `RememberMeService`

`Cookie::set` / `Cookie::get` calls live **only** inside the FuelPHP-side `RememberMeService` implementation. Handlers and resolvers call semantic methods: `$rememberMe->rememberUser($user)`, `$rememberMe->tryReanimate()`, `$rememberMe->forget()`. Cookie names, TTL, and security flags (HttpOnly, Secure, SameSite=Lax) are implementation details of the service, hidden from audience-layer code.

This is the same discipline as `SessionAuthenticatorInterface` (which already hides `\Session::instance()` calls). Without it, handlers in `backend/src/Audience/...` would directly reference `\Cookie::`, binding the framework-agnostic layer to FuelPHP — exactly the leak CLAUDE.md prohibits.

### Decorator chain for login commands

`LogInCommand` flows through the standard ADR-008 chain: Throttle → Security → Logger → Transaction → Handler. Two specifics:

- **Throttle is IP-based via the default decorator behaviour.** The existing `ThrottleLogicTrait` keys anonymous dispatches on `ip:<ip>` automatically (login commands are anonymous by definition). Registering `LogInCommand` in `ThrottleConfigDefaults` with a strict anonymous limit (e.g., 10 attempts per 10 minutes) covers the OWASP-recommended "rate-limit failed authentication attempts" requirement and ships zero new throttle code. Email-based throttling was considered as a defence against credential-stuffing botnets but was deferred: it introduces an account-lockout DoS vector (an attacker who knows a victim's email can lock them out for the window) without solving credential-stuffing fully, and it requires extending `ThrottleLogicTrait` with a per-command key seam. If credential stuffing becomes a measured problem in production, the extension is mechanical: a new optional interface on commands that contributes an additional throttle attempt alongside the default IP attempt. No interface, no extra code today.
- **Security permission is `null`** (explicit public per ADR-008). Login itself must be reachable by anonymous users. The fail-closed property is preserved because login is explicitly registered with `null`, not silently missing from the config.

### Username enumeration defense in the verifier

`PasswordVerifier::verify` always calls `password_verify`, even when the user is not found, against a pre-computed dummy bcrypt hash. This equalizes response time across "user exists, wrong password" and "user does not exist" — the standard countermeasure against email-enumeration via timing analysis. Login error messages are identical in both cases ("Invalid email or password"). The HTTP layer never exposes which factor failed.

## Components

| Component | Layer | Purpose |
|---|---|---|
| `PlaintextPassword` | Domain | VO. Carries the password during login/signup. `__toString` returns `[REDACTED]`. |
| `PasswordHasherInterface` | Domain | `hash()`, `verify()`, `needsRehash()`. |
| `BcryptPasswordHasher` | SK Infra | Bcrypt with configurable cost (default 12). |
| `PasswordVerifierInterface` (+ 3 markers) | Domain | `verify(EmailAddress, PlaintextPassword): ?AuthenticatedUser`. |
| `LocalPasswordVerifier` (abstract) + 3 concrete | SK Infra | Reads user table, runs `password_verify`, equalizes timing. |
| `SessionAuthenticatorInterface` (+ 3 markers) | Domain | `login(AuthenticatedUser)`, `logout()`, `getCurrentUserId()`. |
| `FuelPhpSessionAuthenticator` (abstract) + 3 concrete | FuelPHP Infra | Wraps `\Session::instance($audience)`. Rotates session ID on login/logout. |
| `RememberMeServiceInterface` (+ 3 markers) | Domain | `rememberUser()`, `tryReanimate()`, `forget()`, `forgetAllForUser()`. |
| `SplitTokenRememberMeService` (abstract) + 3 concrete | FuelPHP Infra | Selector/validator generation, rotation, cookie management. |
| `RememberTokenRepositoryInterface` | Domain | `insert()`, `findBySelector()`, `rotate()`, `deleteBySelector()`, `deleteByUser()`, `purgeExpired()`. |
| `MysqlRememberTokenRepository` | FuelPHP Infra | DBAL implementation of the above. |
| `UserRepositoryInterface` (+ 3 markers) | Domain | `findByEmail()`, `findById()`, `getPasswordHashByEmail()`, `updateLastLogin()`. |
| `FuelPhpUserRepository` (abstract) + 3 concrete | FuelPHP Infra | Reads user + roles via JOIN. |
| `AdminSessionResolver` / `PartnerSessionResolver` / `SiteSessionResolver` | FuelPHP Infra | `UserResolverInterface` impls. Try session, fall back to remember-me, reanimate. |
| `LogInCommand` / `LogOutCommand` × 3 audiences | Audience layer | CQRS commands carrying VOs only. |
| `Controller_<Audience>_Auth` × 3 | FuelPHP app | Login/logout endpoints. `get_login` / `post_login` / `action_logout`. Validation, VO construction, command dispatch. |
| `Controller_Audience` (extended by per-audience abstracts) | FuelPHP app | Resolves `$this->queryBus` and `$this->commandBus` once. Audience abstracts add IP check (admin), session resolver wiring, and `redirect()` helper on top. |
| `redirect()` helper on `Controller_<Audience>_Abstract` | FuelPHP app | `redirect(string $path = ''): Response` builds absolute audience URL via `Config::get('audience.urls.<audience>')`. Replaces direct `Response::redirect()` calls because the latter resolves through global `base_url` and lands on the main host. |
| `audience.php` config | FuelPHP app | Maps audience name → absolute base URL, seeded from `APP_URL_ADMIN` / `APP_URL_PARTNER` / `APP_URL`. Autoloaded via `always_load.config`. Single source of truth for audience URLs — used by `redirect()` and any non-HTTP context (workers, cron, mail) that must build audience links. |
| `FuelPhpTransactionManager` | FuelPHP Infra | `TransactionManagerInterface` implementation wrapping `DB::start_transaction / commit_transaction / rollback_transaction`. Required by `CommandTransactionDecorator` — was unbound before auth landed because no command was ever dispatched. |
| `admin_users`, `partner_users`, `customer_users` | DB | Per-audience credential tables. |
| `admin_user_roles`, `partner_user_roles`, `customer_user_roles` | DB | Per-audience role assignment. |
| `remember_tokens` | DB | Single table with audience discriminator. |
| `InvalidCredentialsException` | Domain | Thrown by login handler on verifier failure. Generic message at HTTP layer. |

## Consequences

### Positive

- Login can be written once in FuelPHP and ported to Laravel by replacing only the infrastructure adapters. Audience-layer code (commands, handlers, VOs) is framework-agnostic and migrates verbatim.
- Marker-interface DI is fully type-driven — no string keys, no factory closures, no autowire-by-name. Refactor-safe and statically analyzable.
- Three-table user model preserves per-audience evolution: adding `partner_users.company_name` does not touch the admin or customer schema.
- Split-token remember-me is OWASP-grade and multi-device friendly. Stolen cookies have a narrow window (until the legitimate user's next request rotates the validator); leaked databases yield no usable credentials.
- IP-based throttling on login is delivered by the existing decorator with a config-only change — no infrastructure code modifications required for MVP brute-force protection.
- Cognito remains reachable: a future `AdminCognitoPasswordVerifier` implementing `AdminPasswordVerifierInterface` swaps cleanly with no changes to handlers, controllers, or the security context.
- VO-only commands plus controller-side validation keep the bus surface clean and prevent malformed input from consuming throttle allowance.

### Trade-offs

- Three user tables means three migrations, three repositories, three verifiers, three session authenticators, three remember-me services, three resolvers, three controllers. The marker-interface pattern adds another layer (marker interface + concrete subclass per audience). Total file count is high; per-file LOC is low. The alternative — a single parameterized class wired with named DI bindings — was rejected on the type-safety grounds above.
- Existing per-audience interfaces in the codebase (`UserResolverInterface`, `SecurityConfigInterface`) follow the older one-interface-many-implementations pattern. The auth module introduces the marker-interface pattern alongside, creating temporary inconsistency. Aligning the older interfaces is a separate refactor.
- `remember_tokens.user_id` has no FK (polymorphic across three user tables). Application code must `DELETE FROM remember_tokens WHERE audience = ? AND user_id = ?` whenever a user is deleted. A trigger could enforce this, but adds DB-side complexity for a cleanup case that runs at most a few times per day in practice.
- Three role tables means a "list all roles assigned across all audiences" query needs a UNION. No real use case for that today.
- Session resolution adds two SQL queries per authenticated request (user JOIN roles). Acceptable for server-rendered traffic; cacheable in Redis with a short TTL if profiling shows it matters.

### Deferred Decisions

- **Signup flow** for customers (and possibly partners), including email verification. Out of scope here. Will require a separate ADR covering `email_verification_tokens`, anti-bot measures, and where the email-confirmation gate sits relative to the first login.
- **Password reset via email** with one-time tokens. Same split-token discipline as remember-me. Separate ADR.
- **"Logout from all devices"** requires a `session_version` column on user rows and a check in the resolver. Adds one column to schema and one check to each authenticated request. Defer until a real use case (admin account compromise, mass session invalidation after policy change).
- **Per-audience session timeout** — admin should likely idle out in 30 minutes, customer in 30 days. FuelPHP `Session::instance()` instances accept independent `expiration_time`; the wiring needs three configs. Trivial; deferred until a stated requirement.
- **Password rehash on login** when bcrypt cost changes — `$hasher->needsRehash($currentHash)` after successful verify, re-hash and UPDATE. Adds one UPDATE on the hot path. Defer until the first cost-factor upgrade.
- **Audit log of auth events** — separate `auth_events` table for incident response (`user_id`, `event`, `ip`, `ua`, `occurred_at`). ADR-008 explicitly out-of-scopes audit logging; if regulatory pressure appears, revisit.
- **Email-keyed throttle on login** as a second layer beside the default IP throttle. Useful if credential-stuffing botnets (many IPs, one targeted email) become a measured problem. Requires extending `ThrottleLogicTrait` with an optional per-command key seam (e.g., `ThrottleKeyAwareCommandInterface` with `getThrottleKey(): ?string`) that contributes an additional throttler attempt alongside the default one. Defer until production telemetry justifies it. Carries an account-lockout DoS trade-off that must be balanced via lenient per-email limits and/or CAPTCHA in front.
- **Multi-tab rotation race** — two browser tabs simultaneously presenting the same remember cookie cause one to fail validator-equality after the other rotates. Real but rare; mitigation is either a grace period (old validator valid for 30s after rotation) or rotation only when remaining TTL crosses a threshold. Defer until reported.
- **Migration of existing `UserResolverInterface` / `SecurityConfigInterface` to marker-interface pattern** — separate refactor PR.

### Future Work

- `AdminCognitoPasswordVerifier` (and `partner`, `site` if scope expands) — implements the existing `AdminPasswordVerifierInterface`, swapped in via DI. No changes upstream. Implementation requires the deferred decisions in ADR-008 (env-based driver swap, dev stub).
- Social login (Google / Apple / Facebook) via `league/oauth2-client`. New `oauth_identities` table linking `(provider, provider_user_id)` to one of the local user tables. Login flow becomes "verify OAuth callback → look up local user → `SessionAuthenticator::login($user)`". Same `AuthenticatedUser` and same session path; new controllers, new repository, no changes to the auth core.
- Laravel-side adapters: `EloquentUserRepository`, Laravel-`SessionAuthenticator` (wraps `Auth::guard()`), `LaravelRememberMeService` (wraps Laravel's cookie facade). Bound to the existing marker interfaces. Estimated work at migration time: 1-2 days for the auth module alone.
