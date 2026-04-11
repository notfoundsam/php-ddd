<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\CqrsMessageBus\QueryBusFactory;
use SharedKernel\Infrastructure\CqrsMessageBus\QueryHandlerRegistryInterface;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\InMemoryContainer;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQuery;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQueryHandler;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQueryResponse;

class QueryBusFactoryTest extends TestCase
{
    public function testFactoryComposesMultipleRegistries(): void
    {
        $container = new InMemoryContainer([
            TestQueryHandler::class => new TestQueryHandler(),
        ]);

        $registryA = new class implements QueryHandlerRegistryInterface {
            public function getQueryHandlers(): array
            {
                return [];
            }
        };

        $registryB = new class implements QueryHandlerRegistryInterface {
            public function getQueryHandlers(): array
            {
                return [TestQuery::class => TestQueryHandler::class];
            }
        };

        $factory = new QueryBusFactory($container, [$registryA, $registryB]);
        $bus = $factory();

        $response = $bus->dispatch(new TestQuery('abc'));

        $this->assertInstanceOf(TestQueryResponse::class, $response);
        $this->assertSame('result-for-abc', $response->getData());
    }
}
