<?php

namespace Fuel\Core;

use Blade;
use Jenssegers\Blade\Blade as BladeEngine;

/**
 * Blade facade tests
 *
 * @group App
 * @group Blade
 */
class Test_Blade extends TestCase
{
    private string $cacheDir;

    public function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/blade-test-cache-' . uniqid();
        $engine = new BladeEngine(APPPATH . 'tests/fixtures/blade', $this->cacheDir);
        Blade::setEngineForTesting($engine);
    }

    public function tearDown(): void
    {
        Blade::setEngineForTesting(null);
        if (is_dir($this->cacheDir)) {
            foreach (glob($this->cacheDir . '/*') as $file) {
                @unlink($file);
            }
            @rmdir($this->cacheDir);
        }
    }

    public function test_render_resolves_flat_template_name()
    {
        $output = Blade::render('hello', ['name' => 'World']);

        $this->assertSame("Hello, World!\n", $output);
    }

    public function test_render_resolves_dot_notation_to_subdirectory()
    {
        $output = Blade::render('foo.bar', ['value' => 42]);

        $this->assertSame("dot-notation works: 42\n", $output);
    }

    public function test_render_auto_escapes_interpolated_values()
    {
        $output = Blade::render('escape', ['unsafe' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function test_respond_returns_response_with_given_status()
    {
        $response = Blade::respond('hello', ['name' => 'World'], 418);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(418, $response->status);
        $this->assertStringContainsString('Hello, World!', (string) $response->body());
    }

    public function test_respond_sets_html_content_type()
    {
        $response = Blade::respond('hello', ['name' => 'World']);

        $headers = $response->get_header();
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertSame('text/html; charset=utf-8', $headers['Content-Type']);
    }

    public function test_respond_merges_extra_headers()
    {
        $response = Blade::respond('hello', ['name' => 'World'], 200, [
            'HX-Push-Url' => '/products?q=foo',
        ]);

        $headers = $response->get_header();
        $this->assertSame('/products?q=foo', $headers['HX-Push-Url']);
    }
}
