# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a FuelPHP to Laravel migration project using Domain-Driven Design (DDD) principles. The project consists of three layers:

- **Domain Layer** (`backend/src/`): Framework-agnostic business logic, PHP 7.4 compatible
- **FuelPHP Layer** (`fuelphp/`): Legacy application, PHP 7.4
- **Laravel Layer** (`laravel/`): Target application, PHP 8.1

Both frameworks share the same domain layer via composer. The migration spans over a year with two PHP versions running simultaneously.

## Architecture Rules

### Framework-Agnostic Domain Layer

`backend/src/` must never depend on FuelPHP, Laravel, or any framework. It contains:
- Domain interfaces, value objects, aggregates, events
- Framework-agnostic infrastructure (SQS repository, in-memory implementations, processors, configs)
- Code must be PHP 7.4 compatible (no `static` return types, no enums, no named arguments)

### Framework-Specific Code Placement

Database-backed implementations that use a framework's DB layer live in that framework's directory, not in `backend/src/`:
- FuelPHP: `fuelphp/fuel/packages/infrastructure/classes/` (namespace `Infrastructure\`)
- Laravel: `laravel/app/` (when implemented)

DI container wiring lives in each framework's config:
- FuelPHP: `fuelphp/fuel/app/config/di.php`
- Laravel: service providers (when implemented)

Do not create factory classes that instantiate framework-specific classes inside `backend/src/`. Instead, wire concrete implementations in the framework's DI config.

### Factory configuration source

Framework-specific factories (e.g. `Infrastructure\Redis\RedisClientFactory`) read settings from the framework's native config layer, **not** from `getenv()` directly:

- FuelPHP: `Config::load('<name>', true)` + `Config::get('<name>.<key>')`. The config file (`fuelphp/fuel/app/config/<name>.php`) declares defaults in code and uses `getenv()` only to override them.
- Laravel: read via `config('...')`; the config file consumes `env(...)` the same way.

Reference: `Infrastructure\Redis\RedisClientFactory` + `fuelphp/fuel/app/config/redis.php`. Same pattern as `Blade` (`fuelphp/fuel/app/classes/blade.php`).

Rationale:
- Defaults are version-controlled, in code, in one place per framework — env variables only override.
- Symmetric between FuelPHP and Laravel — Laravel-side factories will read `config(...)` from a file that consumes `env(...)`, identical shape.
- Per-environment overrides come for free via FuelPHP's `app/config/{development,test}/<name>.php` cascade.

`getenv()` directly inside a factory is the **legacy** pattern (`SmsNotifierFactory`); migrate to `Config::get` when touching them. Do not introduce new factories that read env directly.

### Directory Structure
- `backend/src/` - Framework-agnostic backend layer:
  - **Bounded contexts** (DDD model boundaries — own aggregates, domain events, and a **service-style public API**):
    - `Catalog/` - product catalog bounded context (concrete reference for the ADR-011 pattern)
      - `Application/Service/CatalogReadService.php` — public BC API (method calls, not messages)
      - `Application/ReadModel/*` — DTOs the service returns
      - `Domain/`, `Infrastructure/Repository/` — internal
    - `Crm/`, `Marketing/` - placeholders for future BCs (same shape)
  - **Audiences** (BFF layer — own CQRS messages, HTTP-input parsing, security configs; never share queries across audiences; see ADR-011):
    - `Audience/Site/` - public site queries (`Audience\Site\Application\Query\*`); covers both anonymous routes (catalog) and authenticated ones (cart, profile when added) — permissions discriminate
    - `Audience/Admin/` - admin portal commands/queries/roles
    - `Audience/Partner/` - partner portal commands/queries/roles
  - `SharedKernel/` - Shared domain concepts and infrastructure
- `fuelphp/` - Legacy FuelPHP application (PHP 7.4)
  - `fuelphp/fuel/packages/infrastructure/classes/` - FuelPHP-specific infrastructure implementations
  - `fuelphp/fuel/app/classes/controller/{admin,partner,site}/` - audience-specific controllers (flat, no FuelPHP modules; see ADR-013)
  - `fuelphp/fuel/app/config/subdomain.php` - host-based audience dispatch (`admin.php-ddd.test` → `/admin/...`)
  - `fuelphp/fuel/app/classes/blade.php` - `Blade::render` / `Blade::respond` facade (see ADR-012)
- `laravel/` - Laravel 10 application (PHP 8.1)

### Key Architectural Patterns
- **Domain-Driven Design**: Business logic separated into bounded contexts
- **CQRS**: Command/Query separation using interfaces in `SharedKernel/Application/`
- **Two-Tier Event Architecture**: Commands produce only domain events via outbox pattern; background workers process them and may produce async/scheduled events as secondary reactions (see ADR-005)
- **Dependency Injection**: PHP-DI container in FuelPHP (`fuelphp/fuel/app/config/di.php`)
- **Security (RBAC)**: `SecurityCommandDecorator` / `SecurityQueryDecorator` enforce a permission per command/query at dispatch (decorator chain order: Throttle → Security → Logger → Transaction → Handler). Commands/queries declare a permission only — never a role; role→permission mapping lives in `SecurityConfigInterface` per bounded context. `null` permission = explicit public; missing entry = `SecurityConfigurationException` (fail closed). Per-context configs are composed via `SecurityConfigRegistryInterface` mirroring `CommandHandlerRegistryInterface`. See `backend/src/SharedKernel/Domain/Security/` and `backend/src/SharedKernel/Infrastructure/Security/`.
- **Audience-as-BFF + Service-style BC API (ADR-011)**: bounded contexts expose a **service** (`Application/Service/*Service.php`) with typed method calls. They do **not** register handlers on the CQRS bus and do **not** appear in any `SecurityConfig`. Audiences (`Audience/*/Application/Query/*`) own the CQRS messages, parse HTTP input via `*Query::fromHttpInput(array $raw): self`, dispatch through the bus once per page, and delegate to BC services by direct method call. Audience handlers always return an **audience-owned** `*Response` (anti-corruption layer), even when it wraps a single BC read model 1:1. Composite queries are the canonical answer to "one page needs multiple BC reads" — one dispatch, one decorator chain pass, one atomic decision.
- **View rendering: Blade (ADR-012)**: `Blade::render('site.home.index', $data)` / `Blade::respond(...)` in `fuelphp/fuel/app/classes/blade.php`. All templates `.blade.php`, dot-notation, `{{ }}` auto-escape, `{!! !!}` for raw. `auto_filter_output` is **off** in FuelPHP config — Blade handles escaping at the template level. **Do not use** `Fuel\Core\Presenter`; audience query responses are the view-model layer.
- **No FuelPHP modules (ADR-013)**: admin/partner controllers live flat in `app/classes/controller/{admin,partner}/*.php` under underscore convention (`Controller_Admin_Welcome`). Host-based audience dispatch is in `app/config/subdomain.php` (URL prefixes `/admin`, `/partner` added based on Host header before routing).

### Event System Architecture (ADR-005)

Key constraints when working with the event system:
- **Commands produce only domain events** through aggregates. No async or metric events from aggregates.
- **`DomainEventCollector`** accepts only `OutboxEventInterface` — narrowly typed, not a generic event bus.
- **Aggregates** record only `OutboxEventInterface` events via `AggregateRoot::record()`.
- **No listener priorities** — listeners execute in registration order. SQS standard queues don't guarantee ordering.
- **SQS DLQ management** is handled by AWS infrastructure (redrive policies), not application code. Do not implement `getEventById()` or `resetForRetry()` for queue-based repositories.
- **Interface segregation**: `EventRepositoryInterface` for core processing, `FailedEventRepositoryInterface` for DLQ ops on database-backed repos only.
- **Correlation/causation IDs**: Use `$event->withCausation($sourceEvent)` for downstream events. Never pass IDs manually.
- For non-domain events (metrics, etc.), use a dedicated decorator — do not route them through the domain event collector.

### Domain Layer Components
- **Aggregate Roots**: When introduced, place under `<Bc>/Domain/Aggregate/` and extend `SharedKernel\Domain\Aggregate\AggregateRoot`. The base class exists; no concrete aggregate roots yet (Catalog uses a plain `Product` entity)
- **Commands**: Implement `SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface`
- **Queries**: Implement `SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface`; response types implement `QueryResponseInterface` *on the audience side only* (BC read models do not — ADR-011)
- **Events**: Extend `SharedKernel\Domain\EventSystem\AbstractEvent`, implement marker interfaces (`OutboxEventInterface`, `AsyncEventInterface`, `ScheduledEventInterface`)
- **Repositories**: Interfaces in Domain layer, implementations in framework directories
- **Decorators**: Logging decorators for command handlers
- **Redis**: `RedisClientInterface` in Domain, `PhpRedisClient` implementation in Infrastructure with read/write separation via `ReadWriteRedisClient`

### Conventions (read before adding new code)

- **BC public API** = `Application/Service/*Service.php` + `Application/ReadModel/*`. No `Query`/`Handler`/registry inside a BC. ADR-011.
- **Audience query** = `Audience/<Audience>/Application/Query/<UseCase>/{Query,Handler,Response}.php`. Query has `static fromHttpInput(array $raw): self`. Register in `<Audience>QueryHandlerRegistry` and `<Audience>SecurityConfig` (`null` = public per ADR-008). Handler calls BC services as methods, not through the bus. ADR-011.
- **Multi-read pages**: one composite audience query, one dispatch. Reference: `ViewCatalogHomePageQuery`. Don't dispatch two queries from one action.
- **Audience response is always audience-owned**: never return a BC read model directly, even on 1:1 wrappers. ADR-011.
- **Views**: `.blade.php` only, under `fuelphp/fuel/app/views/`. Call sites use `Blade::render('site.home.index', $data)` or `Blade::respond(...)`. No `Fuel\Core\View::forge`, no `Fuel\Core\Presenter`. ADR-012.
- **Controllers**: flat at `fuelphp/fuel/app/classes/controller/<audience>/<name>.php`, underscore convention (`Controller_Site_Home`, `Controller_Admin_Welcome`). Inheritance chain: `<leaf> → Controller_<Audience>_Abstract → Controller_Audience → Fuel\Core\Controller`. `Controller_Audience` (in `controller/audience.php`) resolves `$bus` once. Audience-specific cross-cutting in `controller/<audience>/abstract.php`. No FuelPHP modules. ADR-013.
- **HTMX partial endpoints**: redirect non-HTMX callers to the user-facing URL; set `HX-Push-Url` so HTMX pushes the user-facing URL, not the partial endpoint. Reference: `Controller_Site_Home::action_products`.
- **New top-level namespace in `backend/composer.json`**: run `docker compose exec fuelphp composer -d /app/fuelphp update app/backend` after `make composer-autoload`. The path-repo lock on the fuelphp side doesn't pick up new top-level namespaces from a plain dump.
- **Adding / updating a single dependency**: use `composer require <pkg>:<constraint>` to add, or `composer update <pkg>` to bump a single named package. Never run bare `composer update` — it recomputes the lock for every package whose constraint allows movement and silently bumps unrelated transitive deps (e.g. `dealerdirect/phpcodesniffer-composer-installer` v1.2.0→v1.2.1). When the require line was added by hand, run `composer update <pkg>` — only the named package and its transitive deps are touched. After changing `backend/composer.json`, also run `composer -d /app/fuelphp update app/backend`: the FuelPHP-side `composer.lock` stores a snapshot of `backend`'s require section and its content-hash, and `make composer-autoload` (which only runs `dump-autoload`) does NOT refresh it.
- **Numeric clamps**: prefer `max(N, $x)` / `min(N, $x)` / `min(max($lo, $x), $hi)` over `$x < N ? N : $x` ternaries. The codebase already uses `max()` in `ProductSearchResult`, `FilterFacets`, `RedisThrottler`; align new code with that. Tertiary `?:` is fine for *fallback* semantics (e.g. `$perPage < 1 ? self::DEFAULT : $perPage` where zero means "unset", not "below the floor").
- **ADR style**: state the decision, then its rationale once. Don't repeat the same fact as prose + bullet list + ASCII diagram — pick one form. Cut anything that recaps the codebase rather than explaining a choice.
- **Comments**: default to none. Write one only for non-obvious *why* (hidden constraint, workaround, surprising invariant). Don't restate the code, don't reference callers or PRs, don't write multi-line class headers that duplicate the ADR. Exception: phpstan-level-5 array `@param` shapes (`array<string,mixed>`) and `@throws` are kept — they're load-bearing, not prose. Legacy FuelPHP `@author`/version blocks are not the model.

## Git workflow

- Stage with `git add -u` + explicit new-file paths. Avoid `git add -A` / `git add .` and don't list every tracked file by hand when `-u` covers them.
- Commit messages: one-line conventional-style subject, body only if non-obvious *why* (not a recap of the diff). Two short lines beats six wordy ones.

## Development Commands

### Docker-based Development
```bash
# Initial setup
make install

# Start services
make up

# Stop services
make stop

# Rebuild containers
make build

# Update autoloader
make composer-autoload
```

### Code Quality
```bash
# Run linter (PSR12 + SlevomatCodingStandard)
make lint

# Run unit tests
make test-unit
```

### Frontend Build (Vite)

JS and CSS for both FuelPHP and Laravel are built from a single `frontend/` directory at the repo root with Vite. There is **one** Vite config and **one** manifest — do not add a second Vite config inside `fuelphp/` or `laravel/`.

**Source layout:**
- `frontend/src/site/` — customer-facing site sources. Layered bundles: `core.js`/`core.css` on every page, plus per-page chunks under `pages/`.
- `frontend/src/admin/main.{js,css}` — admin portal bundle (monolithic; CSS framework allowed).
- `frontend/src/partner/main.{js,css}` — partner portal bundle (monolithic; CSS framework allowed).
- Build config (`vite.config.js`, `package.json`, `.gitignore`) lives at `frontend/` root, alongside `src/`. Source-vs-config separation matches React/Vue/Vite community convention.

**Adding a new entry:** create the source file under `src/<area>/` and add its path to the `rollupOptions.input` array in `frontend/vite.config.js`.

**Consuming entries:** the manifest keys are the source paths Vite was given, so views reference entries by their `src/...` path:
- FuelPHP views: `<?= Vite::asset('src/site/core.js') ?>` (CSS+JS), `<?= Vite::css(...) ?>`, `<?= Vite::js(...) ?>`. Helper at `fuelphp/fuel/app/classes/vite.php`. The class is standalone — it does **not** extend `Fuel\Core\Asset` (the path-search + mtime model is incompatible with Vite's content-hashed manifest).
- Laravel views: `@vite(['src/site/core.js'])`. The runtime is reconfigured in `laravel/app/Providers/AppServiceProvider.php` to read the shared `frontend/dist/.vite/manifest.json`.

**Dev workflow:** no HMR / dev server. The `frontend` Docker service runs `vite build --watch`, which rebuilds `frontend/dist/` on every save. Reload the browser to see changes. Builds are ~200ms.

**Make targets:**
```bash
make frontend-install   # docker compose run --rm frontend npm install
make frontend-build     # docker compose run --rm frontend npm run build
```

nginx serves `/build/*` from the shared `frontend/dist/` with `Cache-Control: public, max-age=31536000, immutable`. Both FuelPHP and Laravel emit `/build/<hash>.js` URLs which resolve through nginx.

**Symmetric mount layout for manifest reads:** `frontend/dist` is mounted as `/app/<app>/public/build:ro` in *both* PHP containers. This way each app reads the manifest from `public/build/.vite/manifest.json` — same model on both sides:
- FuelPHP config: `'manifest_path' => DOCROOT . 'build/.vite/manifest.json'`
- Laravel: `Vite::useBuildDirectory('build')` + `useManifestFilename('.vite/manifest.json')` → resolves to `public_path('build/.vite/manifest.json')`

In production, the multistage Docker build copies `frontend/dist/` into each app's `public/build/` directly — dev-only mounts don't constrain prod.

### Direct Commands (inside container)
```bash
# Install dependencies
composer -d fuelphp install
composer -d backend install

# Run PHPUnit tests (backend suite)
backend/vendor/bin/phpunit backend/tests

# Run PHPUnit tests (FuelPHP app suite — view/controller helpers)
cd fuelphp/fuel/core && /app/backend/vendor/bin/phpunit -c phpunit.xml --testsuite app

# Run PHPCS linter
backend/vendor/bin/phpcs --standard=backend/phpcs.xml backend

# Run PHPStan
backend/vendor/bin/phpstan analyse -c backend/phpstan.neon
```

## Testing

### Backend Tests (`backend/tests/`)
- Unit tests only — must run without DI container, database, or message queue
- Test fixtures in `backend/tests/Fixtures/`
- PHPUnit 9.6
- Tests focus on domain logic and framework-agnostic infrastructure

### FuelPHP App Tests (`fuelphp/fuel/app/tests/`)
- Tests for FuelPHP-specific app classes (e.g. `Vite`, `Cookie` overrides) live here, not in `backend/tests/`
- Subdirectories follow FuelPHP convention: `controller/`, `model/`, `view/` (no `presenter/` — Presenter pattern removed per ADR-012)
- File naming: lowercase, single-word filename (e.g. `vite.php`); class named `Test_<Name>` extending `Fuel\Core\TestCase`
- Method names: snake_case (`test_does_a_thing()`)
- Run via `make test-unit` (also runs the backend suite) or `docker compose run --rm --workdir /app/fuelphp/fuel/core fuelphp /app/backend/vendor/bin/phpunit -c phpunit.xml --testsuite app`
- Test fixtures live in `fuelphp/fuel/app/tests/fixtures/`

### Framework Integration Tests
- Tests that require a database, DI container, or framework APIs belong in the framework's test directory, not in `backend/tests/`

## Key Configuration Files

- `Makefile` - Docker-based development commands
- `backend/phpcs.xml` - PHP CodeSniffer configuration (PSR12 + Slevomat rules)
- `backend/composer.json` - Domain layer dependencies and PSR-4 autoloading
- `fuelphp/composer.json` - FuelPHP dependencies, includes `backend` as path repository
- `fuelphp/fuel/app/config/di.php` - PHP-DI container configuration
- `fuelphp/fuel/app/config/repositories.php` - Repository bindings
- `docs/adr/` - Architecture Decision Records (all decisions live here; the legacy `backend/docs/adr/` location is being removed)

## Security Considerations

- Admin controllers extend an abstract controller with IP restriction (`fuelphp/fuel/app/classes/controller/admin/abstract.php`); IP check runs before `parent::before()` so the QueryBus is not resolved for requests about to 404
- Host-based audience boundaries are enforced in `fuelphp/fuel/app/config/subdomain.php`: requests to `/admin` or `/partner` on the main host are rewritten to `_404_`; only `admin.*` / `partner.*` subdomains are routed to those controllers
- Strict types enabled across domain layer (`declare(strict_types=1)`)
