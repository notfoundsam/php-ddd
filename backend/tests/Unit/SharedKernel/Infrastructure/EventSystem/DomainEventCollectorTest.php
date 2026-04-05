<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\EventSystem;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\EventSystem\DomainEventCollector;
use Tests\Fixtures\SharedKernel\EventSystem\OrderCreatedEvent;
use Tests\Fixtures\SharedKernel\EventSystem\TestAggregate;

class DomainEventCollectorTest extends TestCase
{
    private DomainEventCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new DomainEventCollector();
    }

    public function testPullReturnsAndClearsEvents(): void
    {
        $event = new OrderCreatedEvent('order-1');
        $this->collector->push($event);

        $events = $this->collector->pull();
        $this->assertCount(1, $events);
        $this->assertSame($event, $events[0]);

        $this->assertEmpty($this->collector->pull());
    }

    public function testCollectFromAggregate(): void
    {
        $event1 = new OrderCreatedEvent('order-1');
        $event2 = new OrderCreatedEvent('order-2');

        $aggregate = new TestAggregate();
        $aggregate->recordEvent($event1);
        $aggregate->recordEvent($event2);

        $this->collector->collectFromAggregate($aggregate);

        $events = $this->collector->pull();
        $this->assertCount(2, $events);
        $this->assertSame($event1, $events[0]);
        $this->assertSame($event2, $events[1]);
    }

    public function testHasEventsAndGetEventCount(): void
    {
        $this->assertFalse($this->collector->hasEvents());
        $this->assertSame(0, $this->collector->getEventCount());

        $this->collector->push(new OrderCreatedEvent('order-1'));
        $this->collector->push(new OrderCreatedEvent('order-2'));

        $this->assertTrue($this->collector->hasEvents());
        $this->assertSame(2, $this->collector->getEventCount());
    }
}
