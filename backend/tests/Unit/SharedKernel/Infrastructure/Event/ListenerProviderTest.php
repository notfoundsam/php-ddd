<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Event;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Event\EventInterface;
use SharedKernel\Domain\Event\EventListenerInterface;
use SharedKernel\Infrastructure\Event\ListenerProvider;
use Tests\Fixtures\SharedKernel\Infrastructure\Event\OtherTestEvent;
use Tests\Fixtures\SharedKernel\Infrastructure\Event\TestEvent;

class ListenerProviderTest extends TestCase
{
    private ListenerProvider $listenerProvider;

    protected function setUp(): void
    {
        $this->listenerProvider = new ListenerProvider();
    }

    public function testGetListenersForEventReturnsEmptyArrayWhenNoListenersRegistered(): void
    {
        // Arrange
        $event = $this->createMock(EventInterface::class);

        // Act
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        // Assert
        $this->assertIsIterable($listeners);
        $this->assertEmpty($listeners);
    }

    public function testAddListenerRegistersListenerForSpecificEventClass(): void
    {
        // Arrange
        $event = new TestEvent();
        $listener = $this->createMock(EventListenerInterface::class);

        // Act
        $this->listenerProvider->addListener(TestEvent::class, $listener);
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        // Assert
        $this->assertCount(1, $listeners);
        $this->assertContains($listener, $listeners);
    }

    public function testAddMultipleListenersForSameEventClass(): void
    {
        // Arrange
        $event = new TestEvent();
        $listener1 = $this->createMock(EventListenerInterface::class);
        $listener2 = $this->createMock(EventListenerInterface::class);
        $listener3 = $this->createMock(EventListenerInterface::class);

        // Act
        $this->listenerProvider->addListener(TestEvent::class, $listener1);
        $this->listenerProvider->addListener(TestEvent::class, $listener2);
        $this->listenerProvider->addListener(TestEvent::class, $listener3);
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        // Assert
        $this->assertCount(3, $listeners);
        $this->assertContains($listener1, $listeners);
        $this->assertContains($listener2, $listeners);
        $this->assertContains($listener3, $listeners);
    }

    public function testAddListenersForDifferentEventClasses(): void
    {
        // Arrange
        $event1 = new TestEvent();
        $event2 = new OtherTestEvent();

        $listener1 = $this->createMock(EventListenerInterface::class);
        $listener2 = $this->createMock(EventListenerInterface::class);

        // Act
        $this->listenerProvider->addListener(TestEvent::class, $listener1);
        $this->listenerProvider->addListener(OtherTestEvent::class, $listener2);

        $listeners1 = $this->listenerProvider->getListenersForEvent($event1);
        $listeners2 = $this->listenerProvider->getListenersForEvent($event2);

        // Assert
        $this->assertCount(1, $listeners1);
        $this->assertContains($listener1, $listeners1);
        $this->assertNotContains($listener2, $listeners1);

        $this->assertCount(1, $listeners2);
        $this->assertContains($listener2, $listeners2);
        $this->assertNotContains($listener1, $listeners2);
    }

    public function testListenerOrderIsPreserved(): void
    {
        // Arrange
        $event = new TestEvent();
        $listener1 = $this->createMock(EventListenerInterface::class);
        $listener2 = $this->createMock(EventListenerInterface::class);
        $listener3 = $this->createMock(EventListenerInterface::class);

        // Act
        $this->listenerProvider->addListener(TestEvent::class, $listener1);
        $this->listenerProvider->addListener(TestEvent::class, $listener2);
        $this->listenerProvider->addListener(TestEvent::class, $listener3);
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        // Assert
        $this->assertContainsEquals($listener1, $listeners);
        $this->assertContainsEquals($listener2, $listeners);
        $this->assertContainsEquals($listener3, $listeners);
    }
}
