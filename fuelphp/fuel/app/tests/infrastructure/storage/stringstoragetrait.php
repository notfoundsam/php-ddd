<?php

namespace Fuel\Core;

use Infrastructure\Storage\StringStorageTrait;
use InvalidArgumentException;

/**
 * @group App
 * @group Storage
 */
class Test_StringStorageTrait extends TestCase
{
    public function test_build_full_path_strips_leading_slash()
    {
        $host = new StringStorageTraitTestHost();

        $this->assertSame('foo/bar.txt', $host->call('/foo/bar.txt'));
        $this->assertSame('foo/bar.txt', $host->call('foo/bar.txt'));
    }

    public function test_build_full_path_rejects_dot_dot_segments()
    {
        $host = new StringStorageTraitTestHost();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must not contain ".." segments');

        $host->call('foo/../etc/passwd');
    }

    public function test_build_full_path_rejects_leading_dot_dot()
    {
        $host = new StringStorageTraitTestHost();

        $this->expectException(InvalidArgumentException::class);

        $host->call('../etc/passwd');
    }

    public function test_build_full_path_rejects_trailing_dot_dot()
    {
        $host = new StringStorageTraitTestHost();

        $this->expectException(InvalidArgumentException::class);

        $host->call('foo/..');
    }

    public function test_build_full_path_allows_dot_dot_inside_filename()
    {
        $host = new StringStorageTraitTestHost();

        $this->assertSame('foo/file..txt', $host->call('foo/file..txt'));
        $this->assertSame('foo/..bar', $host->call('foo/..bar'));
    }
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
class StringStorageTraitTestHost
{
    use StringStorageTrait;

    public function call(string $path): string
    {
        return $this->buildFullPath($path);
    }

    public function put(string $path, $stream): void
    {
    }

    public function get(string $path)
    {
        return null;
    }
}
