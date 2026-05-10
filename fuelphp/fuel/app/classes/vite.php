<?php

class Vite
{
    /** @var array<string, mixed>|null */
    protected static ?array $manifestCache = null;

    /** @var array<string, mixed>|null */
    protected static ?array $configOverride = null;

    public static function asset(string $entry): string
    {
        return self::css($entry) . self::js($entry);
    }

    public static function css(string $entry): string
    {
        $manifest = self::loadManifest();
        $tags = '';
        foreach (self::collectStylesheets($manifest, $entry) as $cssFile) {
            $href = htmlspecialchars(self::buildUrl($cssFile), ENT_QUOTES, 'UTF-8');
            $tags .= '<link rel="stylesheet" href="' . $href . '">' . "\n";
        }
        return $tags;
    }

    public static function js(string $entry): string
    {
        $manifest = self::loadManifest();
        if (!isset($manifest[$entry])) {
            throw new RuntimeException('Vite manifest entry not found: ' . $entry);
        }
        $src = htmlspecialchars(self::buildUrl($manifest[$entry]['file']), ENT_QUOTES, 'UTF-8');
        return '<script type="module" src="' . $src . '"></script>' . "\n";
    }

    public static function setConfigForTesting(array $config): void
    {
        self::$configOverride = $config;
        self::$manifestCache = null;
    }

    public static function resetConfigForTesting(): void
    {
        self::$configOverride = null;
        self::$manifestCache = null;
    }

    /**
     * @return mixed
     */
    protected static function config(string $key)
    {
        if (self::$configOverride !== null) {
            return array_key_exists($key, self::$configOverride) ? self::$configOverride[$key] : '';
        }
        return Config::get('vite.' . $key, '');
    }

    /**
     * @return array<string, mixed>
     */
    protected static function loadManifest(): array
    {
        if (self::$manifestCache !== null) {
            return self::$manifestCache;
        }
        $path = self::config('manifest_path');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Vite manifest not found at: ' . $path);
        }
        $contents = file_get_contents($path);
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Vite manifest is not valid JSON: ' . $path);
        }
        self::$manifestCache = $decoded;
        return self::$manifestCache;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<int, string>
     */
    protected static function collectStylesheets(array $manifest, string $entry, array $seen = []): array
    {
        if (isset($seen[$entry]) || !isset($manifest[$entry])) {
            return [];
        }
        $seen[$entry] = true;
        $node = $manifest[$entry];
        $css = isset($node['css']) && is_array($node['css']) ? $node['css'] : [];
        if (isset($node['imports']) && is_array($node['imports'])) {
            foreach ($node['imports'] as $imported) {
                $css = array_merge($css, self::collectStylesheets($manifest, $imported, $seen));
            }
        }
        return $css;
    }

    protected static function buildUrl(string $file): string
    {
        $base = self::config('build_path');
        if ($base === '') {
            $base = '/build';
        }
        return rtrim($base, '/') . '/' . ltrim($file, '/');
    }
}
