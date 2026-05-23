<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\CqrsMessageBus\HandlerNotCallableException;
use SharedKernel\Infrastructure\CqrsMessageBus\HandlerNotFoundException;
use SharedKernel\Infrastructure\CqrsMessageBus\HandlerNotResolvableException;
use SharedKernel\Infrastructure\CqrsMessageBus\QueryBus;
use stdClass;
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

    public function testDispatchWrapsContainerMissAsHandlerNotResolvable(): void
    {
        $bus = new QueryBus(new InMemoryContainer([]));
        $bus->register(TestQuery::class, TestQueryHandler::class);

        try {
            $bus->dispatch(new TestQuery('abc'));
            $this->fail('Expected HandlerNotResolvableException');
        } catch (HandlerNotResolvableException $e) {
            $this->assertStringContainsString(TestQuery::class, $e->getMessage());
            $this->assertStringContainsString(TestQueryHandler::class, $e->getMessage());
            $this->assertNotNull($e->getPrevious(), 'Original NotFoundException must be preserved as previous');
        }
    }

    public function testDispatchThrowsHandlerNotCallableWhenResolvedHandlerHasNoInvoke(): void
    {
        $container = new InMemoryContainer([TestQueryHandler::class => new stdClass()]);

        $bus = new QueryBus($container);
        $bus->register(TestQuery::class, TestQueryHandler::class);

        $this->expectException(HandlerNotCallableException::class);
        $this->expectExceptionMessage(TestQuery::class);
        $this->expectExceptionMessage(TestQueryHandler::class);

        $bus->dispatch(new TestQuery('abc'));
    }
}
