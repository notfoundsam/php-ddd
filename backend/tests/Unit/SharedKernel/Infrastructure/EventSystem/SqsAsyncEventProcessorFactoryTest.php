<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\EventSystem;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\EventSystem\SqsAsyncEventProcessorFactory;
use ReflectionMethod;

class SqsAsyncEventProcessorFactoryTest extends TestCase
{
    public function testCreatesInMemoryProcessorForTestEnvironment(): void
    {
        $factory = new SqsAsyncEventProcessorFactory(
            new Environment('test'),
            $this->createMock(EventFactoryInterface::class),
            $this->createMock(ListenerProviderInterface::class),
            $this->createMock(LoggerInterface::class),
            '',
            ''
        );

        $processor = $factory();

        $this->assertInstanceOf(AsyncEventProcessorInterface::class, $processor);
    }

    /**
     * @dataProvider extractEndpointFromUrlProvider
     */
    public function testExtractEndpointFromUrl(string $url, ?string $expected): void
    {
        $factory = new SqsAsyncEventProcessorFactory(
            new Environment('test'),
            $this->createMock(EventFactoryInterface::class),
            $this->createMock(ListenerProviderInterface::class),
            $this->createMock(LoggerInterface::class),
            '',
            ''
        );

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
}
