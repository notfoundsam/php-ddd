<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Redis;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Redis\Exceptions\RedisConnectionException;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Infrastructure\Redis\ReadWriteRedisClient;

class ReadWriteRedisClientTest extends TestCase
{
    private MockObject $readClient;
    private MockObject $writeClient;
    private MockObject $logger;
    private ReadWriteRedisClient $client;

    protected function setUp(): void
    {
        $this->readClient = $this->createMock(RedisClientInterface::class);
        $this->writeClient = $this->createMock(RedisClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->client = new ReadWriteRedisClient($this->readClient, $this->writeClient, $this->logger);
    }

    // --- Read operations route to replica ---

    public function testGetRoutesToReadClient(): void
    {
        $this->readClient->expects($this->once())
            ->method('get')
            ->with('key1')
            ->willReturn('value1');

        $this->writeClient->expects($this->never())->method('get');

        $this->assertSame('value1', $this->client->get('key1'));
    }

    public function testExistsRoutesToReadClient(): void
    {
        $this->readClient->expects($this->once())
            ->method('exists')
            ->with('key1')
            ->willReturn(true);

        $this->writeClient->expects($this->never())->method('exists');

        $this->assertTrue($this->client->exists('key1'));
    }

    public function testMgetRoutesToReadClient(): void
    {
        $keys = ['key1', 'key2'];
        $this->readClient->expects($this->once())
            ->method('mget')
            ->with($keys)
            ->willReturn(['val1', 'val2']);

        $this->writeClient->expects($this->never())->method('mget');

        $this->assertSame(['val1', 'val2'], $this->client->mget($keys));
    }

    public function testKeysRoutesToReadClient(): void
    {
        $this->readClient->expects($this->once())
            ->method('keys')
            ->with('prefix:*')
            ->willReturn(['prefix:1', 'prefix:2']);

        $this->writeClient->expects($this->never())->method('keys');

        $this->assertSame(['prefix:1', 'prefix:2'], $this->client->keys('prefix:*'));
    }

    public function testScanRoutesToReadClient(): void
    {
        $this->readClient->expects($this->once())
            ->method('scan')
            ->with('prefix:*', 0, 100)
            ->willReturn(['cursor' => 5, 'keys' => ['prefix:1']]);

        $this->writeClient->expects($this->never())->method('scan');

        $this->assertSame(['cursor' => 5, 'keys' => ['prefix:1']], $this->client->scan('prefix:*'));
    }

    // --- Write operations route to master ---

    public function testSetRoutesToWriteClient(): void
    {
        $this->writeClient->expects($this->once())
            ->method('set')
            ->with('key1', 'value1', 60)
            ->willReturn(true);

        $this->readClient->expects($this->never())->method('set');

        $this->assertTrue($this->client->set('key1', 'value1', 60));
    }

    public function testDelRoutesToWriteClient(): void
    {
        $this->writeClient->expects($this->once())
            ->method('del')
            ->with(['key1'])
            ->willReturn(1);

        $this->readClient->expects($this->never())->method('del');

        $this->assertSame(1, $this->client->del(['key1']));
    }

    public function testMsetRoutesToWriteClient(): void
    {
        $data = ['key1' => 'val1', 'key2' => 'val2'];
        $this->writeClient->expects($this->once())
            ->method('mset')
            ->with($data)
            ->willReturn(true);

        $this->readClient->expects($this->never())->method('mset');

        $this->assertTrue($this->client->mset($data));
    }

    public function testIncrRoutesToWriteClient(): void
    {
        $this->writeClient->expects($this->once())
            ->method('incr')
            ->with('counter')
            ->willReturn(5);

        $this->readClient->expects($this->never())->method('incr');

        $this->assertSame(5, $this->client->incr('counter'));
    }

    public function testSetnxRoutesToWriteClient(): void
    {
        $this->writeClient->expects($this->once())
            ->method('setnx')
            ->with('lock', '1', 30)
            ->willReturn(true);

        $this->readClient->expects($this->never())->method('setnx');

        $this->assertTrue($this->client->setnx('lock', '1', 30));
    }

    // --- getMaster always routes to write client ---

    public function testGetMasterRoutesToWriteClient(): void
    {
        $this->writeClient->expects($this->once())
            ->method('get')
            ->with('key1')
            ->willReturn('value1');

        $this->readClient->expects($this->never())->method('get');

        $this->assertSame('value1', $this->client->getMaster('key1'));
    }

    // --- Failover from replica to master ---

    public function testGetFailsOverToMasterOnReplicaError(): void
    {
        $this->readClient->expects($this->once())
            ->method('get')
            ->willThrowException(RedisConnectionException::operationFailed('GET', 'connection lost'));

        $this->writeClient->expects($this->once())
            ->method('get')
            ->with('key1')
            ->willReturn('value1');

        $this->assertSame('value1', $this->client->get('key1'));
    }

    public function testExistsFailsOverToMasterOnReplicaError(): void
    {
        $this->readClient->expects($this->once())
            ->method('exists')
            ->willThrowException(RedisConnectionException::operationFailed('EXISTS', 'connection lost'));

        $this->writeClient->expects($this->once())
            ->method('exists')
            ->with('key1')
            ->willReturn(true);

        $this->assertTrue($this->client->exists('key1'));
    }

    public function testMgetFailsOverToMasterOnReplicaError(): void
    {
        $keys = ['key1', 'key2'];

        $this->readClient->expects($this->once())
            ->method('mget')
            ->willThrowException(RedisConnectionException::operationFailed('MGET', 'connection lost'));

        $this->writeClient->expects($this->once())
            ->method('mget')
            ->with($keys)
            ->willReturn(['val1', null]);

        $this->assertSame(['val1', null], $this->client->mget($keys));
    }

    // --- Failover logging ---

    public function testFailoverLogsWarning(): void
    {
        $this->readClient->expects($this->once())
            ->method('get')
            ->willThrowException(RedisConnectionException::operationFailed('GET', 'connection lost'));

        $this->writeClient->expects($this->once())
            ->method('get')
            ->willReturn('value1');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Redis replica failover to master', [
                'operation' => 'GET',
                'error' => 'Redis operation "GET" failed: connection lost',
            ]);

        $this->client->get('key1');
    }

    public function testFailoverWorksWithoutLogger(): void
    {
        $clientWithoutLogger = new ReadWriteRedisClient($this->readClient, $this->writeClient);

        $this->readClient->expects($this->once())
            ->method('get')
            ->willThrowException(RedisConnectionException::operationFailed('GET', 'connection lost'));

        $this->writeClient->expects($this->once())
            ->method('get')
            ->with('key1')
            ->willReturn('value1');

        $this->assertSame('value1', $clientWithoutLogger->get('key1'));
    }

    // --- Ping uses write client first ---

    public function testPingRoutesToWriteClient(): void
    {
        $this->writeClient->expects($this->once())
            ->method('ping')
            ->willReturn(true);

        $this->readClient->expects($this->never())->method('ping');

        $this->assertTrue($this->client->ping());
    }

    public function testPingFailsOverToReadClientOnMasterError(): void
    {
        $this->writeClient->expects($this->once())
            ->method('ping')
            ->willThrowException(RedisConnectionException::operationFailed('PING', 'connection lost'));

        $this->readClient->expects($this->once())
            ->method('ping')
            ->willReturn(true);

        $this->assertTrue($this->client->ping());
    }
}
