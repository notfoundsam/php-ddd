# ADR-012: Blade as the View Engine on the FuelPHP Side

**Status:** Accepted
**Date:** 2026-05-16

## Context

FuelPHP ships with a PHP-template view layer (`Fuel\Core\View`) and an `auto_filter_output` config flag that, when enabled, runs every view variable through `Security::htmlentities` before output. Objects pass through only if they implement `Sanitization`, override `__toString`, or are whitelisted by FQCN in `config.php`.

The first feature in this session (the shop home page) needed to pass DDD value objects and read-model DTOs into templates — `ProductSearchResult`, `FilterFacets`, `Category`, `Brand`, list items. Several approaches were tried in sequence:

1. **Whitelist each DTO** in `security.whitelisted_classes`. Worked for the first feature, scaled badly: every new BC and every new audience-response would add lines to the whitelist; a rename in the BC silently desyncs the whitelist; junior contributors forget. A linear-growth security-baseline file is a slow-burning maintenance debt.
2. **Marker interface `ViewExposable`** added to the whitelist once, with `Security::htmlentities` using `is_a()` for the check. Solved the linear growth. Still leaked a presentation concern (`is this object safe to render?`) into domain DTOs and required discipline ("don't forget to add `implements ViewExposable`") that a missing implementation would expose at runtime, not compile time.
3. **Disable `auto_filter_output` and escape manually** with `<?= htmlspecialchars($x, ENT_QUOTES, 'UTF-8') ?>` everywhere. Solved the typing issue; restored the original problem — every dynamic output in every template is a place to forget escape. The XSS protection-in-depth disappears from the framework and lands on code review.

The deeper observation: `auto_filter_output` is the wrong layer for view safety. It tries to sanitize at the *variable* level (per assignment into view scope) instead of at the *expression* level (per output to HTML). Modern template engines — Blade, Twig — solve this at compile time: the engine itself emits escape calls for `{{ }}`, raw output requires explicit `{!! !!}`, and forgetting to escape is structurally impossible.

The system's migration target is Laravel, which ships Blade as the standard. Adopting Blade on the FuelPHP side now means views written today translate directly when migration time comes, instead of getting rewritten from PHP templates.

## Decision

### Blade is the Only Template Engine

All views use Blade syntax in `.blade.php` files. There is no fallback to plain PHP templates; the project has one template language to learn, debug, and migrate.

`auto_filter_output` is set to `false` in `app/config/config.php`. The framework's whitelist is no longer consulted; the comment in `config.php` explains why for the next reader. Blade's `{{ $x }}` calls `e($x)` (which is `htmlspecialchars($x, ENT_QUOTES, 'UTF-8', false)`) at compile time. Explicit raw output uses `{!! $x !!}` and is the only path that bypasses escape — visible in code review, impossible to write by accident.

### Engine Lives Behind a Standalone Facade, Not a `Fuel\Core\View` Override

The Blade engine (`jenssegers/blade ^1.4`, PHP 7.4-compatible) is wrapped in a small standalone class at `app/classes/blade.php`:

```php
class Blade {
    public static function render(string $name, array $data = []): string;
    public static function respond(string $name, array $data = [], int $status = 200, array $headers = []): Response;
}
```

Controllers call `Blade::respond('site.home.index', [...])`. The API is symmetric with `Vite::asset(...)` (ADR-009) — both are thin static facades over external build/render tools.

An earlier iteration extended `Fuel\Core\View` and registered the override via `Autoloader::add_classes(['View' => …])` (the same mechanism `fuelphp/fuel/app/classes/cookie.php` uses to extend `Fuel\Core\Cookie`). It was rejected: inheriting ~700 lines of `Fuel\Core\View` for almost nothing actually used (`set_safe`, `bind`, `set_global`, filter closures don't compose with Blade), needing reflection to reach `$global_data`, and pretending we still used FuelPHP's view layer when we didn't.

### Templates Use Laravel-Style Dot-Notation

`Blade::render('site.home.index', $data)` resolves to `app/views/site/home/index.blade.php`. `Blade::render('admin.welcome.index', $data)` resolves to `app/views/admin/welcome/index.blade.php`. View paths are audience-prefixed to mirror the controller layout (ADR-013). The convention matches Laravel's `view()` helper — when individual views migrate to Laravel, the call site becomes `view('site.home.index', $data)` and the template path is unchanged.

`@extends('site.layout')` + `@section('content') … @endsection` (with `@yield('content')` in the layout) replace the previous FuelPHP pattern of `View::forge('layouts/site', ['content' => $childView->render()])` + `set_safe`. `@include('site.partials.header')` replaces nested `View::forge(...)->render()` chains. Layout inheritance, partials, control flow, and auto-escape all use the same syntax a Laravel developer already knows.

### Module Views Live in App-Level Views Under a Path Prefix

FuelPHP's module-scoped view paths (`modules/admin/views/welcome/index.php`) do not work with Blade's single-`viewsPath` engine. Module views moved to `app/views/admin/welcome/index.blade.php` and are rendered by `Blade::respond('admin.welcome.index')`. ADR-013 covers the broader removal of FuelPHP modules; this ADR records why module views couldn't stay where they were even if modules had remained.

### `Fuel\Core\Presenter` is Removed From the App

`Presenter` is FuelPHP's "view-model" pattern — a class that prepares data and renders a paired view. In this project's DDD layout, audience query responses (`Audience\Site\Application\Query\…\…Response`) are already the view-model layer (ADR-011). Keeping `Presenter` would have produced two parallel view-model abstractions. The `app/classes/presenter/` directory was deleted; `Controller_Welcome::action_hello` and `action_404` were rewritten as plain controller actions preparing a `$data` array and calling `Blade::respond`.

## Components

- `composer require jenssegers/blade ^1.4` (pulls `illuminate/view ^8.83`; PHP 7.4-compatible).
- `fuelphp/fuel/app/classes/blade.php` — the standalone facade, ~60 lines. Lazy-builds the engine from `fuelphp/fuel/app/config/blade.php` (`views_path`, `cache_path`).
- `fuelphp/fuel/app/config/config.php` — `'auto_filter_output' => false` with a comment naming this ADR.
- `fuelphp/fuel/app/views/` — all `.blade.php`. Compiled-template cache lives in `app/cache/blade/` and is already covered by the existing `/fuel/app/cache/*/*` gitignore rule.
- Removed: `app/classes/presenter/` directory (Welcome presenters were the only consumers) and all `.php` view files.

## Consequences

**Positive:**

- Compile-time auto-escape on every dynamic output. Forgetting to escape is structurally impossible — `{{ }}` always escapes, raw output requires the explicit `{!! !!}` token that is visible in code review.
- One template language across the project. New contributors learn Blade once, not Blade-plus-FuelPHP-templates-plus-the-rule-for-when-to-use-which.
- Domain-level DDD objects pass into views without contortions. No `ViewExposable` markers, no whitelist, no marker discipline.
- Lean facade (~60 lines) instead of a 90-line override that inherited 700+ lines it didn't use. Symmetric with `Vite::asset` — same `app/classes/<tool>.php` pattern for external-tool wrappers.
- Migration to Laravel is a search-and-replace of `Blade::render(` → `view(` and `Blade::respond(` → `response()->view(`. Templates themselves do not change.
- `Presenter` pattern removed; one less abstraction overlapping with audience query responses.

**Negative:**

- One additional Composer dependency tree (`jenssegers/blade` pulls `illuminate/view`, `illuminate/filesystem`, `illuminate/events`, `illuminate/container`, `symfony/finder`, etc.). Pure framework cost is acceptable for the engine we want.
- Compiled-template cache directory (`app/cache/blade/`) needs to exist and be writable. The Blade facade creates it on first render; the parent (`app/cache/`) must be writable by the PHP process in deployments.
- `Fuel\Core\View` is no longer the way to render — any future contributor who copies a FuelPHP tutorial verbatim will write code that fails.
- `auto_filter_output = false` is now site-wide. The framework-level escape pass that previously caught variables passed through `Fuel\Core\View` is gone. The protection rests entirely on Blade being the only render path: any new path that emits HTML must go through `Blade::render` / `Blade::respond` (or escape manually) — there is no global net behind it.

**Migrations and follow-ups:**

- When Laravel takes over a view, the controller imports `view()` from Laravel's helpers and the template moves from `fuelphp/fuel/app/views/foo.blade.php` to `laravel/resources/views/foo.blade.php`. No syntax changes inside the template.
- If a future feature legitimately needs raw HTML in `{{ }}` position (e.g., trusted markdown render), use `{!! $html !!}` and audit at code-review time — the codebase has no pre-baked `e()` helper or unsafe-default escape hatch.
- The `presenter/` directory deletion deliberately leaves `Fuel\Core\Presenter` in the framework untouched; if a future contributor tries `Presenter::forge(...)` the framework will still find it.
