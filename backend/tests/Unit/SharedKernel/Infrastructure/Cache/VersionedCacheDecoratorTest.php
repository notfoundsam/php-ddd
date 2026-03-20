<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Cache;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SharedKernel\Domain\Cache\CacheInterface;
use SharedKernel\Domain\Cache\InvalidCacheArgumentException;
use SharedKernel\Infrastructure\Cache\VersionedCacheDecorator;

class VersionedCacheDecoratorTest extends TestCase
{
    /** @var MockObject|CacheInterface */
    private $mockCache;
    private VersionedCacheDecorator $versionedCache;

    protected function setUp(): void
    {
        $this->mockCache = $this->createMock(CacheInterface::class);
        $this->versionedCache = new VersionedCacheDecorator(
            $this->mockCache,
            'test-context',
            [
                'user' => 'v1.2.0',
                'product' => 'v1.1.0',
            ],
            'v1.0.0'
        );
    }

    public function testGetTransformsKeyAndDelegatesToBaseCache(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->with('test-context:v1.2.0:user:123')
            ->willReturn('cached_value');

        $result = $this->versionedCache->get('user:123');

        $this->assertEquals('cached_value', $result);
    }

    public function testGetUsesDefaultVersionForUnknownNamespace(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->with('test-context:v1.0.0:unknown:456')
            ->willReturn('cached_value');

        $result = $this->versionedCache->get('unknown:456');

        $this->assertEquals('cached_value', $result);
    }

    public function testGetUsesDefaultVersionForKeyWithoutNamespace(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->with('test-context:v1.0.0:simple_key')
            ->willReturn('cached_value');

        $result = $this->versionedCache->get('simple_key');

        $this->assertEquals('cached_value', $result);
    }

    public function testSetTransformsKeyAndDelegatesToBaseCache(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('set')
            ->with('test-context:v1.1.0:product:789', 'value', 3600)
            ->willReturn(true);

        $result = $this->versionedCache->set('product:789', 'value', 3600);

        $this->assertTrue($result);
    }

    public function testDeleteTransformsKeyAndDelegatesToBaseCache(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('delete')
            ->with('test-context:v1.2.0:user:123')
            ->willReturn(true);

        $result = $this->versionedCache->delete('user:123');

        $this->assertTrue($result);
    }

    public function testHasTransformsKeyAndDelegatesToBaseCache(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('has')
            ->with('test-context:v1.2.0:user:123')
            ->willReturn(true);

        $result = $this->versionedCache->has('user:123');

        $this->assertTrue($result);
    }

    public function testClearDelegatesToBaseCache(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $result = $this->versionedCache->clear();

        $this->assertTrue($result);
    }

    public function testGetMultipleTransformsKeysAndDelegatesToBaseCache(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('getMultiple')
            ->with([
                'test-context:v1.2.0:user:123',
                'test-context:v1.1.0:product:456',
            ])
            ->willReturn([
                'test-context:v1.2.0:user:123' => 'user_value',
                'test-context:v1.1.0:product:456' => 'product_value',
            ]);

        $result = $this->versionedCache->getMultiple(['user:123', 'product:456']);

        $this->assertEquals([
            'user:123' => 'user_value',
            'product:456' => 'product_value',
        ], $result);
    }

    public function testSetMultipleTransformsKeysAndDelegatesToBaseCache(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('setMultiple')
            ->with([
                'test-context:v1.2.0:user:123' => 'user_value',
                'test-context:v1.1.0:product:456' => 'product_value',
            ], 3600)
            ->willReturn(true);

        $result = $this->versionedCache->setMultiple([
            'user:123' => 'user_value',
            'product:456' => 'product_value',
        ], 3600);

        $this->assertTrue($result);
    }

    public function testDeleteMultipleTransformsKeysAndDelegatesToBaseCache(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('deleteMultiple')
            ->with([
                'test-context:v1.2.0:user:123',
                'test-context:v1.1.0:product:456',
            ])
            ->willReturn(true);

        $result = $this->versionedCache->deleteMultiple(['user:123', 'product:456']);

        $this->assertTrue($result);
    }

    public function testGetNamespaceVersionReturnsConfiguredVersion(): void
    {
        $version = $this->versionedCache->getNamespaceVersion('user');

        $this->assertEquals('v1.2.0', $version);
    }

    public function testGetNamespaceVersionReturnsDefaultVersionForUnknownNamespace(): void
    {
        $version = $this->versionedCache->getNamespaceVersion('unknown');

        $this->assertEquals('v1.0.0', $version);
    }

    public function testGetNamespaceVersionsReturnsAllConfiguredVersions(): void
    {
        $versions = $this->versionedCache->getNamespaceVersions();

        $this->assertEquals([
            'user' => 'v1.2.0',
            'product' => 'v1.1.0',
        ], $versions);
    }

    public function testWorksWithoutContextPrefix(): void
    {
        $cacheWithoutContext = new VersionedCacheDecorator(
            $this->mockCache,
            '',
            ['user' => 'v1.0.0']
        );

        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->with('v1.0.0:user:123')
            ->willReturn('value');

        $result = $cacheWithoutContext->get('user:123');

        $this->assertEquals('value', $result);
    }

    public function testValidatesKeysUsingCacheKeyTransformer(): void
    {
        $this->expectException(InvalidCacheArgumentException::class);

        $this->versionedCache->get('');
    }

    public function testHandlesIterableKeysInGetMultiple(): void
    {
        $keys = new ArrayIterator(['user:123', 'product:456']);

        $this->mockCache
            ->expects($this->once())
            ->method('getMultiple')
            ->with([
                'test-context:v1.2.0:user:123',
                'test-context:v1.1.0:product:456',
            ])
            ->willReturn([
                'test-context:v1.2.0:user:123' => 'user_value',
                'test-context:v1.1.0:product:456' => 'product_value',
            ]);

        $result = $this->versionedCache->getMultiple($keys);

        $this->assertEquals([
            'user:123' => 'user_value',
            'product:456' => 'product_value',
        ], $result);
    }

    public function testHandlesIterableValuesInSetMultiple(): void
    {
        $values = new ArrayIterator([
            'user:123' => 'user_value',
            'product:456' => 'product_value',
        ]);

        $this->mockCache
            ->expects($this->once())
            ->method('setMultiple')
            ->with([
                'test-context:v1.2.0:user:123' => 'user_value',
                'test-context:v1.1.0:product:456' => 'product_value',
            ], 0)
            ->willReturn(true);

        $result = $this->versionedCache->setMultiple($values);

        $this->assertTrue($result);
    }

    public function testHandlesIterableKeysInDeleteMultiple(): void
    {
        $keys = new ArrayIterator(['user:123', 'product:456']);

        $this->mockCache
            ->expects($this->once())
            ->method('deleteMultiple')
            ->with([
                'test-context:v1.2.0:user:123',
                'test-context:v1.1.0:product:456',
            ])
            ->willReturn(true);

        $result = $this->versionedCache->deleteMultiple($keys);

        $this->assertTrue($result);
    }

    public function testWithEmptyNamespaceVersionsArray(): void
    {
        $cacheWithEmptyVersions = new VersionedCacheDecorator(
            $this->mockCache,
            'test',
            [],
            'v1.0.0'
        );

        $this->mockCache
            ->expects($this->once())
            ->method('get')
            ->with('test:v1.0.0:user:123')
            ->willReturn('value');

        $result = $cacheWithEmptyVersions->get('user:123');

        $this->assertEquals('value', $result);
    }

    public function testComplexKeyTransformation(): void
    {
        $this->mockCache
            ->expects($this->once())
            ->method('set')
            ->with('test-context:v1.2.0:user:profile:settings:123', 'complex_value', 0)
            ->willReturn(true);

        $result = $this->versionedCache->set('user:profile:settings:123', 'complex_value');

        $this->assertTrue($result);
    }
}
