<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\EventSystem;

use PHPUnit\Framework\TestCase;
use Tests\Fixtures\SharedKernel\EventSystem\OrderCreatedEvent;
use Tests\Fixtures\SharedKernel\EventSystem\OrderShippedEvent;
use Tests\Fixtures\SharedKernel\EventSystem\VersionedEvent;

class AbstractEventTest extends TestCase
{
    public function testGeneratesIdWithClassPrefix(): void
    {
        $event = new OrderCreatedEvent('order-1');

        $this->assertStringStartsWith('ordercreated_', $event->getId());
        // "ordercreated_" (13) + 16 hex chars
        $this->assertSame(29, strlen($event->getId()));
    }

    public function testEachEventGetsUniqueId(): void
    {
        $event1 = new OrderCreatedEvent('order-1');
        $event2 = new OrderCreatedEvent('order-1');

        $this->assertNotSame($event1->getId(), $event2->getId());
    }

    public function testDefaultVersionIsOne(): void
    {
        $event = new OrderCreatedEvent('order-1');

        $this->assertSame(1, $event->getVersion());
    }

    public function testCorrelationIdDefaultsToOwnId(): void
    {
        $event = new OrderCreatedEvent('order-1');

        $this->assertSame($event->getId(), $event->getCorrelationId());
    }

    public function testCausationIdIsNullForRootEvent(): void
    {
        $event = new OrderCreatedEvent('order-1');

        $this->assertNull($event->getCausationId());
    }

    public function testWithCausationInheritsCorrelationId(): void
    {
        $rootEvent = new OrderCreatedEvent('order-1');
        $childEvent = (new OrderShippedEvent('order-1'))->withCausation($rootEvent);

        $this->assertSame($rootEvent->getCorrelationId(), $childEvent->getCorrelationId());
        $this->assertSame($rootEvent->getId(), $childEvent->getCausationId());
    }

    public function testWithCausationPreservesCorrelationAcrossChain(): void
    {
        $root = new OrderCreatedEvent('order-1');
        $child = (new OrderShippedEvent('order-1'))->withCausation($root);
        $grandchild = (new OrderCreatedEvent('order-2'))->withCausation($child);

        $this->assertSame($root->getCorrelationId(), $grandchild->getCorrelationId());
        $this->assertSame($child->getId(), $grandchild->getCausationId());
    }

    public function testWithCausationDoesNotMutateOriginal(): void
    {
        $root = new OrderCreatedEvent('order-1');
        $original = new OrderShippedEvent('order-1');
        $originalCorrelationId = $original->getCorrelationId();

        $original->withCausation($root);

        $this->assertSame($originalCorrelationId, $original->getCorrelationId());
        $this->assertNull($original->getCausationId());
    }

    public function testVersionedEventReturnsCustomVersion(): void
    {
        $event = new VersionedEvent('test');

        $this->assertSame(2, $event->getVersion());
    }

    public function testUpcastFromOlderVersion(): void
    {
        $payload = ['name' => 'test'];
        $upcasted = VersionedEvent::upcastToLatestVersion($payload, 1);

        $this->assertSame('active', $upcasted['status']);
    }

    public function testUpcastFromCurrentVersionIsNoop(): void
    {
        $payload = ['name' => 'test', 'status' => 'inactive'];
        $upcasted = VersionedEvent::upcastToLatestVersion($payload, 2);

        $this->assertSame('inactive', $upcasted['status']);
    }

    public function testFromPayloadRestoresEvent(): void
    {
        $payload = [
            'id' => 'ordercreated_abc123',
            'version' => 1,
            'order_id' => 'order-42',
        ];

        $event = OrderCreatedEvent::fromPayload($payload);

        $this->assertSame('ordercreated_abc123', $event->getId());
        $this->assertSame('order-42', $event->getOrderId());
        $this->assertSame(1, $event->getVersion());
    }
}
