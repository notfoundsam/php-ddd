<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Event;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Event\ListenerProviderInterface;
use SharedKernel\Domain\Event\TransactionalEventInterface;
use SharedKernel\Infrastructure\Event\InMemoryEventDispatcher;
use Tests\Fixtures\SharedKernel\Infrastructure\Event\MockEventListenerInterface;

class InMemoryEventDispatcherTest extends TestCase
{
    private ListenerProviderInterface $listenerProvider;
    private InMemoryEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $this->eventDispatcher = new InMemoryEventDispatcher($this->listenerProvider);
    }

    public function testDispatchWithNoEvents(): void
    {
        // ListenerProvider should not be called if no events are provided
        $this->listenerProvider->expects($this->never())
            ->method('getListenersForEvent');

        // Act
        $this->eventDispatcher->dispatch();
    }

    public function testDispatchSingleEvent(): void
    {
        // Arrange
        $event = $this->createMock(TransactionalEventInterface::class);
        $listener1 = $this->createMock(MockEventListenerInterface::class);
        $listener1->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($event));

        $this->listenerProvider->expects($this->once())
            ->method('getListenersForEvent')
            ->with($this->identicalTo($event))
            ->willReturn([$listener1]);

        // Act
        $this->eventDispatcher->dispatch($event);
    }

    public function testDispatchMultipleEvents(): void
    {
        // Arrange
        $event1 = $this->createMock(TransactionalEventInterface::class);
        $event2 = $this->createMock(TransactionalEventInterface::class);

        // Track which events were passed to the listener
        $invokedEvents = [];
        $listener1 = $this->createMock(MockEventListenerInterface::class);
        $listener1->expects($this->exactly(2))
            ->method('__invoke')
            ->willReturnCallback(function (TransactionalEventInterface $event) use (&$invokedEvents) {
                $invokedEvents[] = $event;
            });

        // Track event provider requests to verify the correct order
        $requestedEvents = [];
        $this->listenerProvider->expects($this->exactly(2))
            ->method('getListenersForEvent')
            ->willReturnCallback(function (TransactionalEventInterface $event) use (&$requestedEvents, $listener1) {
                $requestedEvents[] = $event;
                return [$listener1];
            });

        // Act
        $this->eventDispatcher->dispatch($event1, $event2);

        // Assert - verify events were processed in the correct order
        $this->assertCount(2, $requestedEvents);
        $this->assertContainsEquals($event1, $requestedEvents);
        $this->assertContainsEquals($event2, $requestedEvents);

        $this->assertCount(2, $invokedEvents);
        $this->assertContainsEquals($event1, $invokedEvents);
        $this->assertContainsEquals($event2, $invokedEvents);
    }

    public function testDispatchEventWithMultipleListeners(): void
    {
        // Arrange
        $event = $this->createMock(TransactionalEventInterface::class);

        $listener1 = $this->createMock(MockEventListenerInterface::class);
        $listener1->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($event));

        $listener2 = $this->createMock(MockEventListenerInterface::class);
        $listener2->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($event));

        $this->listenerProvider->expects($this->once())
            ->method('getListenersForEvent')
            ->with($this->identicalTo($event))
            ->willReturn([$listener1, $listener2]);

        // Act
        $this->eventDispatcher->dispatch($event);
    }

    public function testDispatchEventWithNoListeners(): void
    {
        // Arrange
        $event = $this->createMock(TransactionalEventInterface::class);

        $this->listenerProvider->expects($this->once())
            ->method('getListenersForEvent')
            ->with($this->identicalTo($event))
            ->willReturn([]);

        // Act - should not throw any exceptions
        $this->eventDispatcher->dispatch($event);
    }
}
