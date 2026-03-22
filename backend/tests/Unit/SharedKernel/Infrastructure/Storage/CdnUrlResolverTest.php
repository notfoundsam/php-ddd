<?php

declare(strict_types=1);

namespace Tests\SharedKernel\Infrastructure\Storage;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\Storage\CdnUrlResolver;

class CdnUrlResolverTest extends TestCase
{
    public function testResolveWithNullPathReturnsEmptyString(): void
    {
        $resolver = $this->createResolver();

        $this->assertEquals('', $resolver->resolve(null));
    }

    public function testResolveWithEmptyStringReturnsEmptyString(): void
    {
        $resolver = $this->createResolver();

        $this->assertEquals('', $resolver->resolve(''));
    }

    public function testResolveWithMatchingPrefix(): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve('public/images/logo.png');

        $this->assertEquals('https://cdn.example.com/logo.png', $result);
    }

    public function testResolveWithNestedPath(): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve('public/images/company/logos/test.jpg');

        $this->assertEquals('https://cdn.example.com/company/logos/test.jpg', $result);
    }

    public function testResolveWithNonMatchingPrefixReturnsEmptyString(): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve('private/documents/secret.pdf');

        $this->assertEquals('', $result);
    }

    public function testResolveWithPrefixOnlyReturnsEmptyString(): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve('public/images/');

        $this->assertEquals('', $result);
    }

    public function testResolveSkipsEmptyDomainMapping(): void
    {
        $resolver = new CdnUrlResolver([
            'public/images/' => '',
            'public/files/' => 'https://files.example.com/',
        ]);

        $this->assertEquals('', $resolver->resolve('public/images/logo.png'));
        $this->assertEquals('https://files.example.com/doc.pdf', $resolver->resolve('public/files/doc.pdf'));
    }

    public function testResolveWithEmptyMappingsReturnsEmptyString(): void
    {
        $resolver = new CdnUrlResolver([]);

        $this->assertEquals('', $resolver->resolve('public/images/logo.png'));
    }

    public function testResolveMatchesFirstPrefix(): void
    {
        $resolver = new CdnUrlResolver([
            'public/' => 'https://public.example.com/',
            'public/images/' => 'https://images.example.com/',
        ]);

        $result = $resolver->resolve('public/images/logo.png');

        $this->assertEquals('https://public.example.com/images/logo.png', $result);
    }

    public function testResolveTrimsTrailingSlashFromDomain(): void
    {
        $resolver = new CdnUrlResolver([
            'uploads/' => 'https://cdn.example.com/',
        ]);

        $result = $resolver->resolve('uploads/file.txt');

        $this->assertEquals('https://cdn.example.com/file.txt', $result);
    }

    public function testResolveHandlesDomainWithoutTrailingSlash(): void
    {
        $resolver = new CdnUrlResolver([
            'uploads/' => 'https://cdn.example.com',
        ]);

        $result = $resolver->resolve('uploads/file.txt');

        $this->assertEquals('https://cdn.example.com/file.txt', $result);
    }

    private function createResolver(): CdnUrlResolver
    {
        return new CdnUrlResolver([
            'public/images/' => 'https://cdn.example.com/',
        ]);
    }
}
