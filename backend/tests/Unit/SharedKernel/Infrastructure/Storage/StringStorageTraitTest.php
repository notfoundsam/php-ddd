<?php

declare(strict_types=1);

namespace Tests\SharedKernel\Infrastructure\Storage;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\Storage\StringStorageTrait;

class StringStorageTraitTest extends TestCase
{
    public function testBuildFullPathStripsLeadingSlash(): void
    {
        $impl = $this->makeTraitHost();

        $this->assertSame('foo/bar.txt', $impl->call('/foo/bar.txt'));
        $this->assertSame('foo/bar.txt', $impl->call('foo/bar.txt'));
    }

    public function testBuildFullPathRejectsDotDotSegments(): void
    {
        $impl = $this->makeTraitHost();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must not contain ".." segments');

        $impl->call('foo/../etc/passwd');
    }

    public function testBuildFullPathRejectsLeadingDotDot(): void
    {
        $impl = $this->makeTraitHost();

        $this->expectException(InvalidArgumentException::class);

        $impl->call('../etc/passwd');
    }

    public function testBuildFullPathRejectsTrailingDotDot(): void
    {
        $impl = $this->makeTraitHost();

        $this->expectException(InvalidArgumentException::class);

        $impl->call('foo/..');
    }

    public function testBuildFullPathAllowsDotDotInsideFilename(): void
    {
        $impl = $this->makeTraitHost();

        $this->assertSame('foo/file..txt', $impl->call('foo/file..txt'));
        $this->assertSame('foo/..bar', $impl->call('foo/..bar'));
    }

    private function makeTraitHost(): StringStorageTraitTestHost
    {
        return new StringStorageTraitTestHost();
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
