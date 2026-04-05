<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

use InvalidArgumentException;

interface EventFactoryInterface
{
    /**
     * Creates an event instance from a stored payload
     *
     * @param class-string<AbstractEvent> $eventType Fully qualified class name
     * @param array<string, mixed> $payload Deserialized event data
     * @return EventInterface
     * @throws EventDeserializationException
     */
    public function createFromPayload(string $eventType, array $payload): EventInterface;

    /**
     * Registers an event class for deserialization
     *
     * @param class-string<EventInterface> $eventType Fully qualified class name
     * @throws InvalidArgumentException If a class doesn't exist or doesn't implement EventInterface
     */
    public function registerEventClass(string $eventType): void;
}
