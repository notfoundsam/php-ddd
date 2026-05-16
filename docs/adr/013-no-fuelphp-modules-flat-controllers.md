# ADR-013: No FuelPHP Modules; Flat Controllers + Subdomain Middleware

**Status:** Accepted
**Date:** 2026-05-16

## Context

FuelPHP supports a `modules/` layer — each module is a self-contained tree (`classes/`, `views/`, `config/routes.php`, `lang/`) under its own PHP namespace. Routes inside a module are loaded only when the route prefix matches; controllers are namespaced (`Admin\Controller_Welcome`); module-scoped views are searched only when the module is loaded. The project originally used two modules: `app/modules/admin/` and `app/modules/partner/`, each with a `Controller_Welcome` and IP-restricted `Controller_Abstract`.

Three things from this session made the module layer redundant:

1. **`Audience/` already exists at the backend layer.** Per ADR-008 and reinforced by ADR-011, `backend/src/Audience/{Admin,Partner,Customer}/` is the canonical place audience-specific commands, queries, security, and roles live. Modules in FuelPHP provide an additional, parallel `Admin\` / `Partner\` PHP namespace that doesn't mean the same thing as the backend `Audience\Admin\` namespace and isn't referenced from it. Two namespaces called `Admin` for the same audience is a source of confusion.

2. **Subdomain routing is host-based, not URL-prefix-based.** `app/config/subdomain.php` is a small middleware loaded by `public/index.php` *before* FuelPHP routing:
   ```php
   // Direct requests to /admin or /partner on the main host are blocked (404)
   if (preg_match('#^(admin|partner)(/|$)#', ltrim($uri, '/'))) {
       $_SERVER['REQUEST_URI'] = '/_404_';
   }
   // admin.php-ddd.test → URI prefixed with /admin internally
   if (preg_match('/^admin\./', $host)) {
       $_SERVER['REQUEST_URI'] = '/admin' . $uri;
   }
   if (preg_match('/^partner\./', $host)) {
       $_SERVER['REQUEST_URI'] = '/partner' . $uri;
   }
   ```
   This is the real audience dispatcher — by Host header, before routing — and it doesn't depend on modules at all. Modules contributed only the `'admin' => 'admin/welcome/index'` route translation, which is a one-line entry in any routes config.

3. **Laravel doesn't have a module system.** Controllers in Laravel are flat under `app/Http/Controllers/` and grouped by route group. Keeping FuelPHP modules now means rewriting away from them later during migration. Flat now = closer to the migration target.

Blade's single `views_path` (ADR-012) also doesn't naturally support module-scoped view discovery; per-module paths would have needed runtime registration for no benefit, since `subdomain.php` is the real dispatcher.

## Decision

### No Modules. Controllers Live Flat in `app/classes/controller/`.

```
app/classes/controller/
  audience.php               ← Controller_Audience (resolves $bus in before())
  welcome.php                ← Controller_Welcome (404 / framework fallbacks; not audience-specific)
  site/
    abstract.php             ← Controller_Site_Abstract  extends Controller_Audience
    home.php                 ← Controller_Site_Home      (catalog: action_index, action_products)
  admin/
    abstract.php             ← Controller_Admin_Abstract extends Controller_Audience + IP restriction
    welcome.php              ← Controller_Admin_Welcome
  partner/
    abstract.php             ← Controller_Partner_Abstract extends Controller_Audience + auth/throttle
    welcome.php              ← Controller_Partner_Welcome
```

All controllers in the **global namespace**, named with FuelPHP's underscore convention (`Controller_<Path>_<Name>`). No `namespace Admin;` or `namespace Partner;` namespacing. Inheritance is a three-level chain: `Controller_Site_Home extends Controller_Site_Abstract extends Controller_Audience extends Fuel\Core\Controller`. `Controller_Audience` resolves `$this->bus = Container::resolve(QueryBusInterface::class)` once for every audience leaf. Audience-specific abstracts add their own cross-cutting (IP restriction for admin, auth/throttle for partner) on top.

### All Routes in One File

`app/config/routes.php` holds every route translation:

```php
'_root_' => 'home/index',
'products' => 'home/products',
'admin' => 'admin/welcome/index',
'partner' => 'partner/welcome/index',
'healthcheck' => function () { … },
'_404_' => 'welcome/404',
'_429_' => function () { … },
```

The module-scoped `modules/admin/config/routes.php` and `modules/partner/config/routes.php` files (each containing one entry) merged into the main file. One place to look for route translations.

### `module_paths` Is Empty

`app/config/config.php` sets `'module_paths' => array()` with a comment explaining the decision. The framework's module loader still exists; it just has no paths to scan. Anyone reading the config sees the empty array and the comment pointing here.

### Subdomain Middleware Stays — It's the Real Audience Dispatcher

`app/config/subdomain.php` is unchanged by this work. It owned host-based routing all along — modules only translated the internal `/admin` URL into a module controller. With controllers flat, the middleware's `/admin` prefix maps directly to `Controller_Admin_Welcome` via the same one-line route translation that used to live inside the module's routes file.

The defensive direct-`/admin`-on-main-host block remains: someone typing `https://php-ddd.test/admin` (i.e., trying to reach admin without the right Host header) still hits `/_404_`. Audience isolation by Host header is preserved.

### Module Views Migrated to `app/views/<audience>/...`

`modules/admin/views/welcome/index.php` → `app/views/admin/welcome/index.blade.php`. Module controllers (now app controllers) call `Blade::respond('admin.welcome.index')`. The path prefix in `app/views/` mirrors the controller path; Blade's single `views_path` (ADR-012) handles them naturally.

## Components

Common base lives at `fuelphp/fuel/app/classes/controller/audience.php` (`Controller_Audience`, resolves `$bus`). Audience leaves live under `controller/{site,admin,partner}/` — each with an `abstract.php` adding audience-specific cross-cutting and concrete action controllers next to it. Views mirror the same layout: `fuelphp/fuel/app/views/{site,admin,partner}/...` (Blade dot-notation per ADR-012, e.g. `site.home.index`, `site.layout`, `site.partials.header`). Route translations are in `app/config/routes.php`; `module_paths` in `app/config/config.php` is `array()` with a comment pointing to this ADR. The `app/modules/` directory is deleted.

## Consequences

**Positive:**

- One `Admin\` namespace concept in the codebase, not two. `Audience\Admin\` (backend) is the only `Admin` in PHP namespace space; FuelPHP-side controllers use underscore naming and live in the global namespace.
- One place to look for routes (`app/config/routes.php`). One place to look for controllers (`app/classes/controller/`). One place for views (`app/views/`).
- Symmetric with Laravel: `app/Http/Controllers/Admin/WelcomeController.php` in Laravel maps mentally onto `app/classes/controller/admin/welcome.php` in FuelPHP. When migration proceeds, controller files move with minimal restructuring.
- Cross-cutting concerns (IP-restriction, auth resolution, query-bus access) live exactly where they always lived — in audience-specific abstract base controllers. Removing modules didn't change this shape, only the file paths.

**Negative:**

- `Fuel\Core\Module` loader is still in the framework codebase but has no work to do. A future contributor who reads FuelPHP documentation and tries to add a module will hit confusing-but-recoverable behavior (the empty `module_paths` means `Module::exists('foo')` returns false; the contributor can either add to `module_paths` and revert this decision deliberately, or follow the flat convention). The `module_paths` comment in `config.php` points here.

- FuelPHP module-scoped routes (the `'foo' => 'foo/bar/baz'` translation that ran automatically when you visited `/foo/...`) no longer auto-discover anything. Every route is explicit in the main file. For a project with two audiences this is fine; if the audience count grows substantially, splitting routes by audience into includes from the main file is a small future refactor that this ADR does not preclude.

**Migrations and follow-ups:**

- New audience pages: add controllers under `app/classes/controller/<audience>/<name>.php`, route translation in `app/config/routes.php`, audience-specific abstract base if not already there.
- Hostname-based audience routing is in `app/config/subdomain.php`. New audiences (e.g., a future `api.php-ddd.test`) add a host-match block there.
- The Laravel migration target inherits this layout 1:1 — Laravel route groups (`Route::prefix('admin')->group(...)`) take over what `subdomain.php` does, controllers move under `app/Http/Controllers/Admin/`, and the underscore-to-PSR-4 rename is mechanical.
