# ADR-008: Security Module (RBAC for CQRS)

**Status:** Accepted
**Date:** 2026-04-25

## Context

The system needs authentication context and authorization for CQRS commands and queries across three audiences (admin panel, partner portal, customer-facing) that will eventually run on different drivers — FuelPHP Auth in the short term for all three, with admin migrating to AWS Cognito JWT and customer to Laravel Auth as the framework migration proceeds. The implementation must be framework-agnostic at the domain layer (PHP 7.4 compatible in `backend/src/`), integrate with the existing CQRS decorator chain (ADR-006) and rate-limiting layer (ADR-007), and support the ongoing FuelPHP-to-Laravel migration without coupling the domain to either framework.

Key requirements:
- Role-based access control on every command and query, with permissions decoupled from roles to prevent role explosion when adding cross-cutting access (e.g., "some managers also need invoice access")
- Pluggable per-driver authentication that hides framework specifics behind a uniform `AuthenticatedUser` value object
- Audience-based ownership of commands, queries, and roles — admin portal owns admin commands and roles; partner portal owns partner's; customer same. Bounded contexts (Crm, Marketing, Billing) own only the domain layer (aggregates, events) and are framework-agnostic libraries that audience commands operate on. No central registry forced on audience teams.
- Fail-closed-by-default: a command without a security configuration entry must throw, not silently allow
- Explicit support for public commands/queries (marketing context will have many) without weakening the fail-closed property
- Coexistence with throttling, logging, and transaction decorators in the existing chain

## Decision

### Authorization is by Permission Only

Commands and queries declare a **permission name** (a string) — never a list of allowed roles. Roles map to permission lists in a separate config section. A user is authorized for a command if any of their roles grants the required permission. The wildcard `*` grants every permission.

This is the standard split that prevents role explosion. When "some managers also need invoice access" appears, the answer is to give those specific users an additional role (e.g., `[manager, invoice_manager]`), not to invent a new role like `manager_with_invoices` or to add another role to the command's allow-list. Roles stay small and composable; commands stay stable.

Listing both `permission` and `roles` per command was considered and rejected — the redundancy reintroduces exactly the role explosion the split is meant to avoid.

### `null` Permission = Public; Missing Entry = Throw

A command/query entry maps to one of:
- A permission string → authentication required, permission checked.
- `null` → explicit public, dispatched without authentication.
- (Missing key) → `SecurityConfigurationException` at dispatch time. **Fail closed.**

A separate `public_commands` allow-list was considered and rejected. It creates two categories of "okay" — declared public (no entry needed) versus declared with permission — that drift independently and can hide forgotten registrations. With the unified map and explicit `null`, declaring a command public is one line right next to other declarations, and forgetting to register a new command produces a loud failure on first dispatch.

A two-bus split (one secured bus, one public bus) was considered and rejected. Marketing has many public routes — forcing public controllers to inject a different bus type creates real friction for the common case, and the fail-closed property is preserved by the `null`-vs-missing rule without needing a separate bus.

### Per-Audience Configs Composed via Registry

Each audience owns a `SecurityConfigInterface` implementation declaring its commands, queries, and roles. Audiences live under `backend/src/Audience/` (`Audience\Admin\…`, `Audience\Partner\…`, public `Audience\Site\…`). Configs are composed at DI wiring time via a `SecurityConfigRegistryInterface` (mirrors `CommandHandlerRegistryInterface` from ADR-006). A `CompositeSecurityConfig` merges entries; duplicate command/query keys throw at first lookup; role permissions union and de-duplicate.

Bounded contexts (`backend/src/Crm/`, `backend/src/Marketing/`) own **only domain code** — aggregates, domain events, repositories interfaces. They contribute nothing to security configs. Commands and queries live in audiences and operate on bounded-context aggregates by calling their domain methods. The same conceptual operation (e.g., creating a lead) typically has separate command classes per audience — `Audience\Admin\…\CreateLeadCommand` vs `Audience\Partner\…\CreateLeadCommand` — because the surrounding concerns differ (history records, originator stamping, additional validations).

Permissions are audience-scoped by convention: `admin.invoice.create`, `partner.lead.create`. Roles in one audience config never reference permission strings owned by another audience — preserving fail-closed semantics across audiences.

A single composite is consulted for every dispatch — there is no per-user-type dispatch in the decorator. User type lives on `AuthenticatedUser` for informational purposes (logging, throttle tier resolution per ADR-007) but is not a key in the security lookup. Authorization is purely role→permission, regardless of which audience the user belongs to.

### Security Interfaces Live in Domain

Both `SecurityConfigInterface` and `SecurityConfigRegistryInterface` live in `SharedKernel\Domain\Security\` alongside `AuthorizationServiceInterface` and `SecurityContextInterface`. Neither has framework dependencies — both are pure contracts. Concrete implementations (`CompositeSecurityConfig`, `SecurityConfigFactory`, `ConfigAuthorizationService`) live in `SharedKernel\Infrastructure\Security\`.

The codebase's existing `CommandHandlerRegistryInterface` (ADR-006) currently lives in Infrastructure, which is an inconsistency with this rule. Migrating it to Domain is tracked as a separate refactor; the inconsistency is small and isolated.

### Authentication is Per-Driver, Hidden Behind `UserResolverInterface`

A single domain interface — `UserResolverInterface::resolve(): ?AuthenticatedUser` — hides how the current user is identified for a request. Each framework/driver provides its own implementation. Per [ADR-014](014-multi-audience-local-authentication.md), three per-audience resolvers are live today:

- `AdminSessionResolver`, `PartnerSessionResolver`, `SiteSessionResolver` (all in `Infrastructure\Security\Resolver\`) — read the host-scoped session first (with a `user_type` guard, see ADR-014), fall back to remember-me token reanimation, repopulate the session on success. Each `Controller_<Audience>_Abstract::before()` resolves the matching marker interface (`AdminUserResolverInterface` / `PartnerUserResolverInterface` / `SiteUserResolverInterface`) and writes the user into `SecurityContextInterface`.
- Future Cognito admin: an SDK-backed `AdminPasswordVerifierInterface` implementation. The session/resolver layer stays the same — only credential verification swaps.
- Future Laravel customer: a Laravel-side `UserResolverInterface` implementation calling `Auth::user()`.

Login is **not** abstracted. Each module's login flow is framework-specific by definition; trying to share a login interface across drivers conflates separate concerns. The seam is the resolver, not login.

### Decorator Chain Order

Outermost to innermost on the command bus:

```
Throttle → Security → Logger → Transaction → Handler
```

Queries omit Transaction. Rationale:
- **Throttle outermost** — rate-limited requests don't reach authorization logic; throttle errors aren't masked as auth errors.
- **Security before Logger** — failed authorization is logged via the existing `*LoggerDecorator` catching `Throwable`. One decorator, one job.
- **Security before Transaction** — a denied command never opens a DB transaction.

### Scaling the Audience Config

A flat `AdminSecurityConfig` works while an audience has a small command/query surface. As the surface grows (estimate: ~30 commands per audience or ~300 lines in the config file), the file becomes hard to scan and PRs that touch a single feature area produce noisy diffs. The known refactor at that point is to split the config into per-feature permission groups and per-role classes, with the top-level config doing only composition.

Sketch:

```
backend/src/Audience/Admin/Infrastructure/Security/
├── AdminSecurityConfig.php             ← composes the parts; ~50 lines regardless of size
├── AdminSecurityConfigRegistry.php
├── Permissions/
│   ├── InvoicePermissions.php          ← constants + commandPermissions() + queryPermissions() + readOnly()/full() bundles
│   ├── UserPermissions.php
│   ├── LeadPermissions.php
│   └── ...
└── Roles/
    ├── AdminRole.php                   ← NAME constant + permissions() composing across feature groups
    ├── StaffRole.php
    ├── StaffInvoiceRole.php
    └── OutsourcerRole.php
```

`AdminSecurityConfig` becomes:

```php
public function getCommandPermissions(): array
{
    return array_merge(
        InvoicePermissions::commandPermissions(),
        UserPermissions::commandPermissions(),
        // ...
    );
}

public function getRolePermissions(): array
{
    return [
        AdminRole::NAME        => ['*'],
        StaffRole::NAME        => StaffRole::permissions(),
        StaffInvoiceRole::NAME => StaffInvoiceRole::permissions(),
    ];
}
```

A role like `StaffRole::permissions()` reads as composition over feature areas (`array_merge(InvoicePermissions::readOnly(), UserPermissions::readOnly(), …)`) instead of a flat list of dozens of permission strings.

This is **not** done today — the audience configs are minimal stubs. The pattern is documented here so the refactor target is known; the migration is mechanical when the time comes.

### Out of Scope

The following are explicitly **not** part of this module:

- **Login / credential verification** — per-framework concern. Each module's login controller writes whatever its driver requires.
- **Session and token refresh management** — framework concern. SimpleAuth's session machinery, Cognito's refresh tokens, and Laravel's session driver are each their framework's responsibility.
- **Audit logging subsystem** — the existing `CommandLoggerDecorator` and `QueryLoggerDecorator` log every dispatch including thrown exceptions. A dedicated `SecurityAuditLoggerInterface` would duplicate this responsibility.
- **Per-instance policies** (e.g., "admin can edit users, but only in their own partner org") — deferred. The architecture allows extension: the decorator can later consult an optional `PolicyResolverInterface` after the permission check passes. No code today.
- **Row scoping** ("partner manager sees only invoices for their org") — not the security module's job. Query handlers receive `SecurityContext`, read the current user, and pass scope to repositories. A generic post-filtering decorator was considered and rejected: it breaks pagination and counts, hits performance, and re-implements per-entity knowledge already in repositories.
- **Field masking / sensitive-field redaction** — presentation-layer concern. A domain object should always carry its real values; redaction belongs in the API resource / view-model layer that serializes to the caller.
- **Standardized error message handling** — exceptions carry technical info; the HTTP layer maps them to generic 401 / 403 responses. A dedicated security error handler would duplicate the HTTP layer's responsibility.

## Components

| Component | Layer | Purpose |
|---|---|---|
| `AuthenticatedUser` | Domain | Value object: id, email, roles, type. `hasRole(string): bool` helper. |
| `UserType` | Domain | Constants: ADMIN, PARTNER, CUSTOMER, ANONYMOUS. |
| `SecurityContextInterface` | Domain | Request-scoped current user. |
| `UserResolverInterface` | Domain | Per-driver seam: `resolve(): ?AuthenticatedUser`. |
| `AuthorizationServiceInterface` | Domain | `isAllowed(AuthenticatedUser, string $permission): bool`. |
| `SecurityConfigInterface` | Domain | `getCommandPermissions()`, `getQueryPermissions()`, `getRolePermissions()`. |
| `UnauthenticatedException` | Domain | Permission required, no user in context. Extends `RuntimeException`. |
| `UnauthorizedException` | Domain | User present, lacks permission. Carries `getRequiredPermission()`. |
| `SecurityConfigurationException` | Domain | Command/query not registered in any config. Extends `LogicException`. |
| `SecurityConfigRegistryInterface` | Domain | Composition seam — audiences contribute via this. |
| `CompositeSecurityConfig` | Infrastructure | Merges per-context configs; throws on duplicate command/query keys; unions roles. |
| `SecurityConfigFactory` | Infrastructure | `__invoke()`-style factory taking registries, returns composite. |
| `ConfigAuthorizationService` | Infrastructure | Walks user roles against config; supports `*` wildcard. |
| `SecurityCommandDecorator` | Infrastructure | `CommandBusInterface` decorator; enforces config-based authorization at dispatch. |
| `SecurityQueryDecorator` | Infrastructure | `QueryBusInterface` decorator; same logic, different return type. |
| `FuelPhpSecurityContext` | FuelPHP infra | Per-request `SecurityContext` storage. |
| `AdminSessionResolver` / `PartnerSessionResolver` / `SiteSessionResolver` | FuelPHP infra | Per-audience `UserResolverInterface` impls per [ADR-014](014-multi-audience-local-authentication.md): try session first, fall back to remember-me reanimation. Wired in the matching `Controller_<Audience>_Abstract::before()`. |
| `AdminSecurityConfig` + `AdminSecurityConfigRegistry` | Admin audience | Roles `admin`, `staff`, `outsourcer`, `staff_invoice`; `LogInCommand` / `LogOutCommand` registered as `null` permission (public). |
| `PartnerSecurityConfig` + `PartnerSecurityConfigRegistry` | Partner audience | Roles `partner_owner`, `partner_member`; `LogInCommand` / `LogOutCommand` registered as `null` permission (public). |
| `SiteSecurityConfig` + `SiteSecurityConfigRegistry` | Site audience | No roles today; `LogInCommand` / `LogOutCommand` and public catalog queries (`ViewCatalogHomePageQuery`, `SearchCatalogProductsQuery`) registered as `null` permission. |

## Consequences

### Positive

- Permission-only authorization scales without role explosion — adding cross-cutting access means assigning an existing role, not editing every affected command.
- Fail-closed-by-default: forgetting to register a new command fails loudly on first dispatch, not silently in production.
- Per-bounded-context configs preserve DDD ownership boundaries — context teams edit their own files only.
- Composition pattern matches the existing `CommandHandlerRegistryInterface` shape — one mental model for both handler registration and security config.
- Per-driver resolvers cleanly separate "which framework auth?" from "what permissions?" — the same `AuthenticatedUser` flows through to identical CQRS authorization regardless of driver.
- Decorator chain order ensures denied commands don't open transactions or get logged as successes.
- Out-of-scope decisions (audit, masking, row scoping, policies) keep the module's surface area small and intentional.

### Trade-offs

- Some logic duplication between `SecurityCommandDecorator` and `SecurityQueryDecorator` — the two operate on different bus interfaces with different return types (`void` vs `QueryResponseInterface`), so a shared trait or base class would obscure two small bodies for negligible reuse benefit. Two ~40-line classes is the lesser evil.
- One `SecurityConfig` file per audience — config grows linearly with audiences (small, fixed set: admin, partner, customer). Acceptable.
- Row scoping is deferred to query handlers individually — requires consistent discipline across handler authors. A generic decorator was rejected as the wrong tool, but the cost is that "filter by tenant" logic isn't centralized.
- Role names are strings — typos in `getRolePermissions()` keys vs. role assignments produce silent denials, not boot-time errors. Mitigated by per-audience constants (e.g., `AdminSecurityConfig::ROLE_ADMIN = 'admin'`) but not enforced.
- Permission strings are similarly typo-prone. Mitigated by per-context constants but not enforced.

### Deferred Decisions

These are real questions raised during design that were intentionally not resolved:

- ~~**Multi-user-type session collision in dev.**~~ Resolved by [ADR-014](014-multi-audience-local-authentication.md): a single host-scoped session cookie per audience-subdomain, with a `user_type` discriminator in the session blob so each `*SessionResolver` refuses to honour a blob belonging to another audience.
- ~~**Env-based resolver swap (SimpleAuth dev → Cognito prod for admin).**~~ Superseded by [ADR-014](014-multi-audience-local-authentication.md): SimpleAuth was replaced by per-audience session resolvers. The seam for future Cognito is `AdminPasswordVerifierInterface` (the credential-check primitive) — swap the DI binding to a Cognito-backed implementation; the session layer stays untouched.
- **Idle session timeout for authenticated users.** PHP sessions have one lifetime per session, not per key. If authenticated sessions need a different (typically shorter) idle window than anonymous sessions, the resolver must enforce a `last_activity` timestamp and clear the user's session keys when stale. Not implemented; defer until a real requirement (e.g., admin panel inactivity timeout) materializes.
- ~~**Where `Package::load('auth')` lives.**~~ Resolved by [ADR-014](014-multi-audience-local-authentication.md): SimpleAuth is no longer used; the FuelPHP `auth` package is no longer loaded at controller level.

### Future Work

- `CognitoJwtUserResolver` for admin panel when admin module lands in production.
- Laravel `UserResolverInterface` implementation when the Laravel layer reaches authenticated CQRS calls.
- Authenticated routes inside the Site audience (cart, profile) — currently only the public read queries are wired; cart/profile permissions land when those features ship.
- Per-instance policy extension point if/when contextual checks become necessary. The decorator can resolve an optional `PolicyResolverInterface` per command FQCN and invoke `$policy->check($user, $command)` after permission passes — no breaking changes required.
