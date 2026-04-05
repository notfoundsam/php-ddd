<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\EventSystem;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\EventSystem\EventInterface;
use SharedKernel\Domain\EventSystem\EventListenerInterface;
use SharedKernel\Domain\EventSystem\OutboxEventInterface;
use SharedKernel\Infrastructure\EventSystem\ListenerProvider;
use Tests\Fixtures\SharedKernel\EventSystem\OrderCreatedEvent;
use Tests\Fixtures\SharedKernel\EventSystem\OrderShippedEvent;

class ListenerProviderTest extends TestCase
{
    private ListenerProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ListenerProvider();
    }

    public function testReturnsEmptyForUnregisteredEvent(): void
    {
        $event = new OrderCreatedEvent('order-1');
        $listeners = $this->provider->getListenersForEvent($event);

        $this->assertEmpty($listeners);
    }

    public function testReturnsListenerForExactEventClass(): void
    {
        $listener = $this->createMock(EventListenerInterface::class);
        $this->provider->addListener(OrderCreatedEvent::class, $listener);

        $event = new OrderCreatedEvent('order-1');
        $listeners = $this->provider->getListenersForEvent($event);

        $this->assertCount(1, $listeners);
        $this->assertSame($listener, $listeners[0]);
    }

    public function testReturnsListenerRegisteredForInterface(): void
    {
        $listener = $this->createMock(EventListenerInterface::class);
        $this->provider->addListener(OutboxEventInterface::class, $listener);

        $event = new OrderCreatedEvent('order-1');
        $listeners = $this->provider->getListenersForEvent($event);

        $this->assertCount(1, $listeners);
    }

    public function testReturnsListenerRegisteredForBaseEventInterface(): void
    {
        $listener = $this->createMock(EventListenerInterface::class);
        $this->provider->addListener(EventInterface::class, $listener);

        $event = new OrderCreatedEvent('order-1');
        $listeners = $this->provider->getListenersForEvent($event);

        $this->assertCount(1, $listeners);
    }

    public function testCombinesListenersFromClassAndInterface(): void
    {
        $classListener = $this->createMock(EventListenerInterface::class);
        $interfaceListener = $this->createMock(EventListenerInterface::class);

        $this->provider->addListener(OrderCreatedEvent::class, $classListener);
        $this->provider->addListener(OutboxEventInterface::class, $interfaceListener);

        $event = new OrderCreatedEvent('order-1');
        $listeners = $this->provider->getListenersForEvent($event);

        $this->assertCount(2, $listeners);
    }

    public function testDoesNotReturnListenersForUnrelatedEvent(): void
    {
        $listener = $this->createMock(EventListenerInterface::class);
        $this->provider->addListener(OrderCreatedEvent::class, $listener);

        $event = new OrderShippedEvent('order-1');
        $listeners = $this->provider->getListenersForEvent($event);

        $this->assertEmpty($listeners);
    }

    public function testCacheIsInvalidatedWhenListenerAdded(): void
    {
        $event = new OrderCreatedEvent('order-1');

        $this->assertEmpty($this->provider->getListenersForEvent($event));

        $listener = $this->createMock(EventListenerInterface::class);
        $this->provider->addListener(OrderCreatedEvent::class, $listener);

        $this->assertCount(1, $this->provider->getListenersForEvent($event));
    }

    public function testPreservesRegistrationOrder(): void
    {
        $listener1 = $this->createMock(EventListenerInterface::class);
        $listener2 = $this->createMock(EventListenerInterface::class);
        $listener3 = $this->createMock(EventListenerInterface::class);

        $this->provider->addListener(OrderCreatedEvent::class, $listener1);
        $this->provider->addListener(OrderCreatedEvent::class, $listener2);
        $this->provider->addListener(OrderCreatedEvent::class, $listener3);

        $event = new OrderCreatedEvent('order-1');
        $listeners = $this->provider->getListenersForEvent($event);

        $this->assertSame($listener1, $listeners[0]);
        $this->assertSame($listener2, $listeners[1]);
        $this->assertSame($listener3, $listeners[2]);
    }
}
