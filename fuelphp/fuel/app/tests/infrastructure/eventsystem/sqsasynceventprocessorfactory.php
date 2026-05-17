<?php

namespace Fuel\Core;

use Infrastructure\EventSystem\SqsAsyncEventProcessorFactory;
use ReflectionMethod;
use RuntimeException;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\Logger\LoggerInterface;

/**
 * @group App
 * @group EventSystem
 */
class Test_SqsAsyncEventProcessorFactory extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Config::load('queue', 'queue', true, true);
    }

    public function tearDown(): void
    {
        Config::load('queue', 'queue', true, true);
        parent::tearDown();
    }

    public function test_creates_in_memory_processor_for_test_environment()
    {
        Config::set('queue.async_events.queue_url', '');
        Config::set('queue.async_events.region', '');

        $factory = $this->buildFactory(new Environment('test'));

        $this->assertInstanceOf(AsyncEventProcessorInterface::class, $factory());
    }

    public function test_creates_in_memory_processor_for_development_when_queue_url_is_empty()
    {
        Config::set('queue.async_events.queue_url', '');
        Config::set('queue.async_events.region', '');

        $factory = $this->buildFactory(new Environment('development'));

        $this->assertInstanceOf(AsyncEventProcessorInterface::class, $factory());
    }

    public function test_throws_for_development_when_queue_url_is_malformed()
    {
        Config::set('queue.async_events.queue_url', 'not-a-url');
        Config::set('queue.async_events.region', 'us-east-1');

        $factory = $this->buildFactory(new Environment('development'));

        $this->expectException(RuntimeException::class);
        $factory();
    }

    /**
     * @dataProvider extractEndpointFromUrlProvider
     */
    public function test_extract_endpoint_from_url(string $url, ?string $expected)
    {
        $factory = $this->buildFactory(new Environment('test'));

        $reflection = new ReflectionMethod($factory, 'extractEndpointFromUrl');
        $reflection->setAccessible(true);

        $this->assertSame($expected, $reflection->invoke($factory, $url));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public function extractEndpointFromUrlProvider(): array
    {
        return [
            'url with port' => [
                'http://elasticmq:9324/queue/async-events',
                'http://elasticmq:9324',
            ],
            'url without port' => [
                'https://sqs.ap-northeast-1.amazonaws.com/123/queue-name',
                'https://sqs.ap-northeast-1.amazonaws.com',
            ],
            'invalid url' => [
                'not-a-url',
                null,
            ],
        ];
    }

    private function buildFactory(Environment $environment): SqsAsyncEventProcessorFactory
    {
        return new SqsAsyncEventProcessorFactory(
            $environment,
            $this->createMock(EventFactoryInterface::class),
            $this->createMock(ListenerProviderInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }
}
