<?php

namespace Fuel\Core;

use RuntimeException;
use Vite;

/**
 * Vite asset helper tests
 *
 * @group App
 * @group Vite
 */
class Test_Vite extends TestCase
{
    private string $fixtureManifest;

    public function setUp(): void
    {
        $this->fixtureManifest = APPPATH . 'tests/fixtures/vite-manifest-sample.json';
    }

    public function tearDown(): void
    {
        Vite::resetConfigForTesting();
    }

    public function test_asset_emits_hashed_css_and_js_tags_for_entry()
    {
        Vite::setConfigForTesting([
            'manifest_path' => $this->fixtureManifest,
            'build_path' => '/build',
        ]);

        $output = Vite::asset('src/site/core.js');

        $this->assertStringContainsString('/build/assets/core-XyZ98765.css', $output);
        $this->assertStringContainsString('<link rel="stylesheet"', $output);
        $this->assertStringContainsString('/build/assets/core-AbCdEf12.js', $output);
        $this->assertStringContainsString('<script type="module"', $output);
    }

    public function test_asset_includes_chunk_imported_css()
    {
        Vite::setConfigForTesting([
            'manifest_path' => $this->fixtureManifest,
            'build_path' => '/build',
        ]);

        $output = Vite::asset('src/site/pages/search.js');

        $this->assertStringContainsString('/build/assets/search-VwXy7890.css', $output);
        $this->assertStringContainsString('/build/assets/shared-AbCd1234.css', $output);
        $this->assertStringContainsString('/build/assets/search-PqRsTu34.js', $output);
    }

    public function test_css_returns_only_link_tags()
    {
        Vite::setConfigForTesting([
            'manifest_path' => $this->fixtureManifest,
            'build_path' => '/build',
        ]);

        $output = Vite::css('src/site/core.js');

        $this->assertStringContainsString('<link rel="stylesheet"', $output);
        $this->assertStringNotContainsString('<script', $output);
    }

    public function test_js_returns_only_script_tag()
    {
        Vite::setConfigForTesting([
            'manifest_path' => $this->fixtureManifest,
            'build_path' => '/build',
        ]);

        $output = Vite::js('src/site/core.js');

        $this->assertStringContainsString('<script type="module"', $output);
        $this->assertStringNotContainsString('<link', $output);
    }

    public function test_missing_manifest_entry_throws_clear_exception()
    {
        Vite::setConfigForTesting([
            'manifest_path' => $this->fixtureManifest,
            'build_path' => '/build',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite manifest entry not found: nonexistent/entry.js');

        Vite::js('nonexistent/entry.js');
    }

    public function test_missing_manifest_file_throws_clear_exception()
    {
        Vite::setConfigForTesting([
            'manifest_path' => '/no/such/manifest.json',
            'build_path' => '/build',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vite manifest not found at: /no/such/manifest.json');

        Vite::js('src/site/core.js');
    }

    public function test_custom_build_path_is_respected()
    {
        Vite::setConfigForTesting([
            'manifest_path' => $this->fixtureManifest,
            'build_path' => '/static/v2',
        ]);

        $output = Vite::js('src/site/core.js');

        $this->assertStringContainsString('/static/v2/assets/core-AbCdEf12.js', $output);
    }
}
