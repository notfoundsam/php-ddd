<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Cache;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Cache\InvalidCacheArgumentException;
use SharedKernel\Infrastructure\Cache\CacheKeyTransformer;

class CacheKeyTransformerTest extends TestCase
{
    public function testExtractNamespaceFromKeyWithNamespace(): void
    {
        $namespace = CacheKeyTransformer::extractNamespace('user:123');

        $this->assertEquals('user', $namespace);
    }

    public function testExtractNamespaceFromKeyWithoutNamespace(): void
    {
        $namespace = CacheKeyTransformer::extractNamespace('simple_key');

        $this->assertEquals('default', $namespace);
    }

    public function testExtractNamespaceFromKeyWithMultipleColons(): void
    {
        $namespace = CacheKeyTransformer::extractNamespace('user:profile:123');

        $this->assertEquals('user', $namespace);
    }

    public function testExtractNamespaceFromEmptyKey(): void
    {
        $namespace = CacheKeyTransformer::extractNamespace('');

        $this->assertEquals('default', $namespace);
    }

    public function testBuildVersionedKeyWithContextPrefix(): void
    {
        $versionedKey = CacheKeyTransformer::buildVersionedKey(
            'user:123',
            'v1.2.0',
            'core'
        );

        $this->assertEquals('core:v1.2.0:user:123', $versionedKey);
    }

    public function testBuildVersionedKeyWithoutContextPrefix(): void
    {
        $versionedKey = CacheKeyTransformer::buildVersionedKey(
            'user:123',
            'v1.2.0'
        );

        $this->assertEquals('v1.2.0:user:123', $versionedKey);
    }

    public function testBuildVersionedKeyWithEmptyContextPrefix(): void
    {
        $versionedKey = CacheKeyTransformer::buildVersionedKey(
            'user:123',
            'v1.2.0',
            ''
        );

        $this->assertEquals('v1.2.0:user:123', $versionedKey);
    }

    public function testValidateKeyWithValidKey(): void
    {
        $this->expectNotToPerformAssertions();

        CacheKeyTransformer::validateKey('valid_key');
        CacheKeyTransformer::validateKey('user:123');
        CacheKeyTransformer::validateKey('a');
        CacheKeyTransformer::validateKey('key-with-dashes');
        CacheKeyTransformer::validateKey('key_with_underscores');
        CacheKeyTransformer::validateKey('key.with.dots');
    }

    public function testValidateKeyThrowsExceptionForEmptyKey(): void
    {
        $this->expectException(InvalidCacheArgumentException::class);

        CacheKeyTransformer::validateKey('');
    }

    public function testValidateKeyThrowsExceptionForTooLongKey(): void
    {
        // 513 characters, exceeds limit 512
        $longKey = str_repeat('a', 513);

        $this->expectException(InvalidCacheArgumentException::class);
        $this->expectExceptionMessage('key too long (max 512 characters)');

        CacheKeyTransformer::validateKey($longKey);
    }

    public function testValidateKeyThrowsExceptionForKeyWithNewline(): void
    {
        $this->expectException(InvalidCacheArgumentException::class);
        $this->expectExceptionMessage('key contains invalid characters');

        CacheKeyTransformer::validateKey("key\nwith\nnewlines");
    }

    public function testValidateKeyThrowsExceptionForKeyWithTab(): void
    {
        $this->expectException(InvalidCacheArgumentException::class);
        $this->expectExceptionMessage('key contains invalid characters');

        CacheKeyTransformer::validateKey("key\twith\ttabs");
    }

    public function testValidateKeyThrowsExceptionForKeyWithCarriageReturn(): void
    {
        $this->expectException(InvalidCacheArgumentException::class);
        $this->expectExceptionMessage('key contains invalid characters');

        CacheKeyTransformer::validateKey("key\rwith\rcarriage\rreturns");
    }

    public function testValidateKeyThrowsExceptionForKeyWithNullByte(): void
    {
        $this->expectException(InvalidCacheArgumentException::class);
        $this->expectExceptionMessage('key contains invalid characters');

        CacheKeyTransformer::validateKey("key\0with\0null");
    }

    public function testValidateKeyThrowsExceptionForKeyWithVerticalTab(): void
    {
        $this->expectException(InvalidCacheArgumentException::class);
        $this->expectExceptionMessage('key contains invalid characters');

        CacheKeyTransformer::validateKey("key\vwith\vvertical\vtabs");
    }

    public function testValidateKeyThrowsExceptionForKeyWithFormFeed(): void
    {
        $this->expectException(InvalidCacheArgumentException::class);
        $this->expectExceptionMessage('key contains invalid characters');

        CacheKeyTransformer::validateKey("key\fwith\fform\ffeed");
    }

    public function testValidateKeyWithMaximumLength(): void
    {
        // Exactly 512 characters
        $maxLengthKey = str_repeat('a', 512);

        $this->expectNotToPerformAssertions();

        CacheKeyTransformer::validateKey($maxLengthKey);
    }

    /**
     * @dataProvider validKeyProvider
     */
    public function testValidateKeyWithVariousValidKeys(string $key): void
    {
        $this->expectNotToPerformAssertions();

        CacheKeyTransformer::validateKey($key);
    }

    public function validKeyProvider(): array
    {
        return [
            ['simple'],
            ['user:123'],
            ['product:category:electronics'],
            ['cache-key-with-dashes'],
            ['cache_key_with_underscores'],
            ['cache.key.with.dots'],
            ['MixedCaseKey'],
            ['key123with456numbers'],
            // Spaces should be allowed
            ['key with spaces'],
            // Special characters should be allowed except the forbidden ones
            ['!@#$%^&*()'],
        ];
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testValidateKeyWithVariousInvalidKeys(string $key, string $expectedMessage): void
    {
        $this->expectException(InvalidCacheArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        CacheKeyTransformer::validateKey($key);
    }

    public function invalidKeyProvider(): array
    {
        return [
            ['', 'Cache key cannot be empty'],
            [str_repeat('a', 513), 'key too long (max 512 characters)'],
            ["key\nwith\nnewline", 'key contains invalid characters'],
            ["key\twith\ttab", 'key contains invalid characters'],
            ["key\rwith\rcarriage", 'key contains invalid characters'],
            ["key\0with\0null", 'key contains invalid characters'],
            ["key\vwith\vvertical", 'key contains invalid characters'],
            ["key\fwith\fform", 'key contains invalid characters'],
        ];
    }
}
