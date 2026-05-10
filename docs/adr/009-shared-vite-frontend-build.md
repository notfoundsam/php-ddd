# ADR-009: Shared Vite Frontend Build for FuelPHP and Laravel

**Status:** Accepted
**Date:** 2026-05-03

## Context

The project is migrating from FuelPHP (PHP 7.4) to Laravel (PHP 8.1) over a year-plus, with both frameworks running simultaneously and sharing the domain layer at `backend/src/`. The frontend story before this decision was inconsistent: FuelPHP had no JS/CSS build tooling at all (only static files under `fuelphp/public/assets/`), while Laravel shipped with its default Vite + `laravel-vite-plugin` setup running from `laravel/`.

This is untenable for the migration:
- As views move from FuelPHP to Laravel one by one, their JS/CSS would have to be re-implemented or duplicated under each app's own build pipeline.
- Two parallel toolchains double maintenance (two `package.json` files, two CI build steps, two Docker setups).
- Shared frontend concerns — design tokens, the customer-site core bundle, common widgets — have no natural home if neither app owns "the build".

The system has three audiences with different needs:
- **Customer-facing public site** (`php-ddd.test`) — search-and-cart workflow, mostly static pages with light JS interaction. Cold-start performance matters because users land here from search engines. HTMX is the chosen interaction model. Custom designs per page may be needed; CSS frameworks may not fit.
- **Admin portal** (`admin.php-ddd.test`) — IP-restricted, authenticated, low-traffic, repeat users. CSS framework is fine; first-load performance is not critical.
- **Partner portal** (`partner.php-ddd.test`) — same characteristics as admin.

Key requirements:
- Single source of truth for all frontend code, accessible to both PHP apps.
- Different bundle strategies per audience without forcing a uniform model.
- FuelPHP must consume the build without coupling the legacy PHP code to Node.js or to framework specifics.
- PHP 7.4 compatibility for any FuelPHP-side PHP code.
- Production deployment uses multistage Docker builds; dev workflow runs through the existing Docker Compose + traefik setup.

## Decision

### Single `frontend/` Directory at the Repo Root

All frontend source lives in a new `frontend/` directory at the repo root, parallel to `backend/`, `fuelphp/`, `laravel/`. One `package.json`, one `vite.config.js`, one output directory. Both PHP apps consume the same manifest.

A per-framework setup (separate Vite configs in `fuelphp/` and `laravel/`) was considered and rejected. Over the multi-year migration we will move views from FuelPHP to Laravel; with a shared build, the views' asset references stay valid through the move. With per-framework builds, every migrated view would force a duplicate frontend rewrite.

### Vite as Build Tool, No Dev Server

Vite produces content-hashed bundles + a manifest. The `frontend` Docker service runs `vite build --watch`, which rebuilds `frontend/dist/` on file save in ~200ms. There is **no Vite dev server, no HMR, no `vite.php-ddd.test` hostname, no `@vite/client`**.

HMR over wss-through-traefik was implemented and verified working, then deliberately removed. The cost (dedicated TLS-terminated subdomain, traefik route, `frontend/public/hot` file dance, `dev_server_url` config + dev-mode branching in the FuelPHP `Vite` class, three dev-mode unit tests) was substantial. The benefit (avoid a browser reload on each save) is small for a project where pages are mostly static and JS interactions are light. `vite build --watch` is a meaningful productivity step over running `npm run build` manually, without paying the HMR complexity.

If interactive work later demands HMR, this decision is revisitable — the source layout and helper class don't preclude it.

### Bundle Strategy

- **Customer site (layered):** a `core` chunk (HTMX + base CSS, shared across every public page) and per-page chunks under `src/site/pages/`. The browser caches `core` once; subsequent pages load only the small page-specific chunk. With long cache headers on hashed filenames, this minimizes both first-paint and repeat-visit cost.
- **Admin and Partner (monolithic per audience):** one bundle each. Authenticated, repeat users; first-load weight doesn't matter. Single bundle is the simplest mental model and matches how a CSS framework is naturally organized.

A single global bundle for the customer site was rejected — the search page is the most-used entry, often a cold-start landing point, so dragging in cart/item code on first paint is wasteful. Per-page self-contained bundles (no shared `core`) were also rejected — HTMX would be redownloaded as part of every page bundle, defeating the cache.

### `Vite` Helper Class on the FuelPHP Side, NOT Extending `Fuel\Core\Asset`

FuelPHP views consume the build via a new standalone `Vite` static class at `fuelphp/fuel/app/classes/vite.php`. The class reads the manifest, resolves entries to hashed filenames, and emits `<link>` + `<script type="module">` tags. Surface: `Vite::asset($entry)`, `Vite::css($entry)`, `Vite::js($entry)`.

Extending `Fuel\Core\Asset` (the precedent set by `fuelphp/fuel/app/classes/cookie.php`) was considered and rejected. The core Asset class is built around (a) multi-path search via `find_file()`, (b) mtime-based cache busting (`add_mtime`), (c) a stateful instance model with groups and indent levels. Vite has none of these — one manifest, content-hashed filenames, one source root. Extending Asset would mean overriding ~80% of methods to no-op, with no behavior reused. A standalone class is honest about the model difference and keeps the surface tight.

Laravel uses its built-in `@vite()` Blade directive, with `Illuminate\Foundation\Vite` reconfigured in `AppServiceProvider::boot()` to read the shared manifest:
```php
Vite::useBuildDirectory('build');
Vite::useManifestFilename('.vite/manifest.json');
```

### Symmetric Mount Layout: `frontend/dist` as `public/build/` in Both PHP Containers

The shared build output is mounted into each PHP container at the **same logical path** — `<app>/public/build/` — via Docker Compose:
```yaml
fuelphp:  - ./frontend/dist:/app/fuelphp/public/build:ro
laravel:  - ./frontend/dist:/app/laravel/public/build:ro
```
Both apps read the manifest via `public_path('build/.vite/manifest.json')`; both emit `/build/<hash>.js` URLs. Same model on both sides.

A symlink at `laravel/public/build` → `../../frontend/dist` was implemented first and rejected. The symlink doesn't survive Windows clones cleanly, requires a separate `make frontend-symlink` target, and is solving a problem that docker volume mounts already solve. With multistage prod builds (the user's deployment pattern), the prod Dockerfile copies `frontend/dist/` into each app's `public/build/` directly — no dev-only mount or symlink applies.

A direct cross-app mount (`./frontend/dist:/app/frontend/dist:ro` for FuelPHP, `./frontend/dist:/app/laravel/public/build:ro` for Laravel) was also rejected — the asymmetry made the FuelPHP `manifest_path` a `DOCROOT . '../../frontend/dist/...'` mess, with no upside.

`laravel-vite-plugin` was dropped from `frontend/package.json` once HMR was removed — the plugin's value was its dev-server integration. Vite's native `rollupOptions.input` produces the same output without it.

## Components

**Source structure** (under `frontend/`):
- `package.json` — devDeps `vite@^5`, runtime dep `htmx.org@^2`. Scripts: `build`, `watch`.
- `vite.config.js` — `build.outDir: 'dist'`, `build.manifest: true`, `rollupOptions.input` listing the six entry points (`src/site/core.js`, three `src/site/pages/*.js`, `src/admin/main.js`, `src/partner/main.js`).
- `src/` — all source code lives here. Subdirectories `site/`, `admin/`, `partner/` map to the three audiences. CSS is `import`ed from the corresponding JS entry.
- `dist/` — gitignored; build output. `dist/.vite/manifest.json` is the source of truth for PHP-side asset resolution.

**FuelPHP-side files:**
- `fuelphp/fuel/app/classes/vite.php` — the helper class. PHP 7.4 compatible, no namespace (FuelPHP app-class autoload convention, mirrors `cookie.php`). Includes `setConfigForTesting()` / `resetConfigForTesting()` test seams.
- `fuelphp/fuel/app/config/vite.php` — `manifest_path` (`DOCROOT . 'build/.vite/manifest.json'`), `build_path` (`/build`).
- `fuelphp/fuel/app/tests/view/vite.php` — 7 unit tests covering manifest parsing, chunked CSS resolution, missing-entry / missing-manifest exceptions, custom `build_path`. Runs via `make test-unit` (delegates to FuelPHP's native PHPUnit setup at `fuel/core/phpunit.xml`, testsuite `app`).

**Laravel-side files:**
- `laravel/app/Providers/AppServiceProvider.php` — `boot()` configures `Vite::useBuildDirectory('build')` + `Vite::useManifestFilename('.vite/manifest.json')`.
- Removed: `laravel/vite.config.js`, `laravel/package.json`, `laravel/resources/{css,js}/`. Laravel no longer runs its own Vite.

**Infrastructure:**
- `docker-compose.yml` — adds `frontend` service (`node:20-alpine`, runs `npm install && npm run watch`); mounts `./frontend/dist` into nginx (at `/app/frontend/dist:ro`) and into both PHP containers (at `/app/<app>/public/build:ro`). Named volume `php-ddd-frontend-node-modules` keeps Linux `node_modules` isolated from the host filesystem.
- `dev-tools/nginx_default.conf` — `location /build/ { alias /app/frontend/dist/; add_header Cache-Control "public, max-age=31536000, immutable" always; }`. Both FuelPHP and Laravel virtual hosts are served from the same nginx server block, so the location applies to both.
- `Makefile` — `frontend-install`, `frontend-build` targets. `install:` runs `frontend-install`. `test-unit:` runs both backend and FuelPHP-app PHPUnit suites.

## Consequences

**Positive:**
- One mental model for frontend across both PHP apps. View migrations from FuelPHP to Laravel keep their asset references intact.
- Frontend complexity stays in one tree (`frontend/`), out of both PHP app roots.
- The customer site's layered bundles minimize first-paint cost on the search page (the cold-start landing point).
- Long-cache headers on content-hashed filenames make repeat visits effectively free.
- The `Vite` helper class is small (~100 lines) and standalone — easy to test, easy to understand.
- Production deployment via multistage Docker is unconstrained — the build artifacts are just files; the prod Dockerfile decides where they go.

**Negative:**
- Adds a Node.js toolchain to the project. New devs need to understand `npm`, Vite, and the `frontend` Docker service.
- `node_modules/` lives in a named Docker volume invisible to the host IDE — a small ergonomic cost for JS-heavy work (mitigated by running `npm install` on the host once for IDE intelligence; the container has its own copy).
- View call sites carry the `src/...` path: `Vite::asset('src/site/core.js')`. The named-entries form was tried but Vite's manifest still keys by source path regardless of input config, so abstraction would require an extra mapping layer for no real benefit.
- No HMR. Devs reload the browser to see JS/CSS changes. Acceptable for the project's mostly-static interaction model; revisitable if heavy interactive work emerges.

**Migrations and follow-ups:**
- Existing `fuelphp/public/assets/{css,js,img,fonts}` content is unchanged by this work; it migrates page-by-page into `frontend/src/` as views are touched.
- CSS framework choice for admin/partner is deferred. When chosen, install via `frontend/package.json` and import from `src/admin/main.css` / `src/partner/main.css`.
- TypeScript migration is deferred. Plain JS for now matches the light interaction model.
