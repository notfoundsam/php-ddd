<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Cache;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SharedKernel\Infrastructure\Cache\InMemoryCache;
use ArrayIterator;

class InMemoryCacheTest extends TestCase
{
    private InMemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
    }

    public function testSetAndGetSuccessfully(): void
    {
        $result = $this->cache->set('test_key', 'test_value', 3600);
        $this->assertTrue($result);

        $retrieved = $this->cache->get('test_key');
        $this->assertEquals('test_value', $retrieved);
    }

    public function testGetReturnsNullWhenKeyDoesNotExist(): void
    {
        $result = $this->cache->get('non_existent_key');
        $this->assertNull($result);
    }

    public function testSetWithZeroTtlMeansNoExpiration(): void
    {
        $this->cache->set('no_expire_key', 'test_value', 0);
        $result = $this->cache->get('no_expire_key');

        $this->assertEquals('test_value', $result);
    }

    public function testDeleteRemovesKeyFromCache(): void
    {
        $this->cache->set('test_key', 'test_value', 3600);
        $result = $this->cache->delete('test_key');

        $this->assertTrue($result);
        $this->assertNull($this->cache->get('test_key'));
    }

    public function testDeleteReturnsTrueForNonExistentKey(): void
    {
        $result = $this->cache->delete('non_existent_key');
        $this->assertTrue($result);
    }

    public function testOverwritingKeyUpdatesValue(): void
    {
        $this->cache->set('test_key', 'initial_value', 3600);
        $this->cache->set('test_key', 'updated_value', 3600);

        $result = $this->cache->get('test_key');
        $this->assertEquals('updated_value', $result);
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set('test_key', 'test_value', 3600);

        $this->assertTrue($this->cache->has('test_key'));
    }

    public function testHasReturnsFalseForNonExistentKey(): void
    {
        $this->assertFalse($this->cache->has('non_existent_key'));
    }

    public function testGetMultipleReturnsAllKeys(): void
    {
        $this->cache->set('key1', 'value1', 3600);
        $this->cache->set('key2', 'value2', 3600);

        $result = $this->cache->getMultiple(['key1', 'key2', 'non_existent']);

        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'non_existent' => null,
        ];

        $this->assertEquals($expected, $result);
    }

    public function testSetMultipleSetsAllValues(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $result = $this->cache->setMultiple($values, 3600);
        $this->assertTrue($result);

        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertEquals('value3', $this->cache->get('key3'));
    }

    public function testSetMultipleWithEmptyArray(): void
    {
        $result = $this->cache->setMultiple([], 3600);
        $this->assertTrue($result);
    }

    public function testDeleteMultipleDeletesAllKeys(): void
    {
        $this->cache->set('key1', 'value1', 3600);
        $this->cache->set('key2', 'value2', 3600);
        $this->cache->set('key3', 'value3', 3600);

        $result = $this->cache->deleteMultiple(['key1', 'key3']);
        $this->assertTrue($result);

        $this->assertFalse($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function testDeleteMultipleWithEmptyArray(): void
    {
        $result = $this->cache->deleteMultiple([]);
        $this->assertTrue($result);
    }

    public function testClearRemovesAllKeys(): void
    {
        $this->cache->set('key1', 'value1', 3600);
        $this->cache->set('key2', 'value2', 3600);

        $result = $this->cache->clear();
        $this->assertTrue($result);

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testTtlExpiration(): void
    {
        $key = 'expiring_key';
        $value = 'test_value';
        $pastTime = time() - 10;

        // Manually set expiry to simulate an expired key
        $reflection = new ReflectionClass($this->cache);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $expiryProperty = $reflection->getProperty('expiry');
        $expiryProperty->setAccessible(true);

        $cache = $cacheProperty->getValue($this->cache);
        $expiry = $expiryProperty->getValue($this->cache);

        $cache[$key] = $value;
        $expiry[$key] = $pastTime;

        $cacheProperty->setValue($this->cache, $cache);
        $expiryProperty->setValue($this->cache, $expiry);

        // Key should be treated as expired
        $this->assertNull($this->cache->get($key));
        $this->assertFalse($this->cache->has($key));
    }

    public function testIterableSupport(): void
    {
        $keys = new ArrayIterator(['key1', 'key2']);
        $values = new ArrayIterator(['key1' => 'value1', 'key2' => 'value2']);

        $this->cache->setMultiple($values, 3600);
        $result = $this->cache->getMultiple($keys);

        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $this->assertEquals($expected, $result);
    }
}
