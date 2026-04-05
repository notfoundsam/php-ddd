<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\EventSystem\EventFactoryInterface;

final class EventFactoryFactory
{
    private OutboxEventRegistry $outboxRegistry;
    private AsyncEventRegistry $asyncRegistry;
    private ScheduledEventRegistry $scheduledEventRegistry;

    public function __construct(
        OutboxEventRegistry $outboxRegistry,
        AsyncEventRegistry $asyncRegistry,
        ScheduledEventRegistry $scheduledEventRegistry
    ) {
        $this->outboxRegistry = $outboxRegistry;
        $this->asyncRegistry = $asyncRegistry;
        $this->scheduledEventRegistry = $scheduledEventRegistry;
    }

    public function __invoke(): EventFactoryInterface
    {
        $factory = new EventFactory();

        // Register outbox events
        foreach ($this->outboxRegistry->getOutboxEventClasses() as $eventClass) {
            $factory->registerEventClass($eventClass);
        }

        // Register async events
        foreach ($this->asyncRegistry->getAsyncEventClasses() as $eventClass) {
            $factory->registerEventClass($eventClass);
        }

        // Register scheduled events
        foreach ($this->scheduledEventRegistry->getScheduledEventClasses() as $eventClass) {
            $factory->registerEventClass($eventClass);
        }

        return $factory;
    }
}
