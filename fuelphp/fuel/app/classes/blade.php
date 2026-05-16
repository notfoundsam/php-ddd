<?php

use Jenssegers\Blade\Blade as BladeEngine;

/**
 * Standalone wrapper around `jenssegers/blade` for FuelPHP-side view rendering.
 *
 * Why a standalone class (not extending `Fuel\Core\View`):
 * - We don't reuse any of Fuel's View internals (`set_safe`, `bind`, `set_global`,
 *   auto-filter pipeline). Inheriting ~700 lines for a 15-line job is gratuitous.
 * - Honest naming: `Blade::render` says exactly what runs.
 * - Symmetric with `Vite::asset`: both are thin static facades over a build-tool/engine.
 * - The API maps 1:1 onto Laravel's `view('site.home.index', $data)` helper, so migration is
 *   a rename, not a redesign.
 *
 * Templates use dot-notation: `'site.home.index'` resolves to
 * `app/views/site/home/index.blade.php`. Auto-escape via `{{ $x }}`, explicit raw via
 * `{!! $x !!}`. Layouts/sections/includes follow Laravel-Blade syntax.
 */
class Blade
{
    private static ?BladeEngine $engine = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $name, array $data = []): string
    {
        return self::engine()->render($name, $data);
    }

    /**
     * Convenience: render the view and wrap it in a FuelPHP Response with the given
     * status code and optional headers. Most controllers just need this.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function respond(string $name, array $data = [], int $status = 200, array $headers = []): \Fuel\Core\Response
    {
        $response = \Fuel\Core\Response::forge(self::render($name, $data), $status);
        foreach ($headers as $key => $value) {
            $response->set_header($key, $value);
        }
        return $response;
    }

    /**
     * Test seam: swap the engine (e.g. with a stub) and then reset.
     */
    public static function setEngineForTesting(?BladeEngine $engine): void
    {
        self::$engine = $engine;
    }

    private static function engine(): BladeEngine
    {
        if (self::$engine === null) {
            \Fuel\Core\Config::load('blade', true);
            $viewsPath = \Fuel\Core\Config::get('blade.views_path');
            $cachePath = \Fuel\Core\Config::get('blade.cache_path');
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0775, true);
            }
            self::$engine = new BladeEngine($viewsPath, $cachePath);
        }
        return self::$engine;
    }
}
