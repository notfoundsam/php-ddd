<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\EventSystem;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\EventSystem\EventDeserializationException;
use SharedKernel\Infrastructure\EventSystem\EventFactory;
use Tests\Fixtures\SharedKernel\EventSystem\OrderCreatedEvent;
use stdClass;

class EventFactoryTest extends TestCase
{
    private EventFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EventFactory();
    }

    public function testCreateFromPayloadWithRegisteredEvent(): void
    {
        $this->factory->registerEventClass(OrderCreatedEvent::class);

        $event = $this->factory->createFromPayload(OrderCreatedEvent::class, [
            'id' => 'ordercreated_test123',
            'version' => 1,
            'order_id' => 'order-42',
        ]);

        $this->assertInstanceOf(OrderCreatedEvent::class, $event);
        $this->assertSame('ordercreated_test123', $event->getId());
    }

    public function testCreateFromPayloadThrowsForUnregisteredEvent(): void
    {
        $this->expectException(EventDeserializationException::class);

        $this->factory->createFromPayload(OrderCreatedEvent::class, []);
    }

    public function testRegisterEventClassRejectsNonExistentClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->registerEventClass('NonExistent\\Class');
    }

    public function testRegisterEventClassRejectsNonEventClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->registerEventClass(stdClass::class);
    }
}
