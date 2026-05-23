<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\CqrsMessageBus\HandlerNotFoundException;
use SharedKernel\Infrastructure\CqrsMessageBus\QueryBus;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\InMemoryContainer;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQuery;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQueryHandler;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQueryResponse;

class QueryBusTest extends TestCase
{
    public function testDispatchReturnsHandlerResponse(): void
    {
        $container = new InMemoryContainer([TestQueryHandler::class => new TestQueryHandler()]);

        $bus = new QueryBus($container);
        $bus->register(TestQuery::class, TestQueryHandler::class);

        $response = $bus->dispatch(new TestQuery('abc'));

        $this->assertInstanceOf(TestQueryResponse::class, $response);
        $this->assertSame('result-for-abc', $response->getData());
    }

    public function testRegisterDoesNotResolveHandlerEagerly(): void
    {
        $bus = new QueryBus(new InMemoryContainer([]));
        $bus->register(TestQuery::class, TestQueryHandler::class);

        $this->expectNotToPerformAssertions();
    }

    public function testDispatchThrowsForUnregisteredQuery(): void
    {
        $bus = new QueryBus(new InMemoryContainer([]));

        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionMessage(TestQuery::class);

        $bus->dispatch(new TestQuery('abc'));
    }
}
