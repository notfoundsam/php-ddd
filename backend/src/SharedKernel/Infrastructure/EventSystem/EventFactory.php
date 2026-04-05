<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use InvalidArgumentException;
use SharedKernel\Domain\EventSystem\AbstractEvent;
use SharedKernel\Domain\EventSystem\EventDeserializationException;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\EventInterface;
use Throwable;

final class EventFactory implements EventFactoryInterface
{
    /**
     * @var array<class-string<EventInterface>, true>
     */
    private array $eventMap = [];

    public function createFromPayload(string $eventType, array $payload): EventInterface
    {
        if (!isset($this->eventMap[$eventType])) {
            throw EventDeserializationException::unknownEventType($eventType);
        }

        try {
            /** @var class-string<AbstractEvent> $eventType */
            return $eventType::fromPayload($payload);
        } catch (Throwable $e) {
            throw EventDeserializationException::failedToDeserialize($eventType, $e);
        }
    }

    public function registerEventClass(string $eventType): void
    {
        if (!class_exists($eventType)) {
            throw new InvalidArgumentException("Event class not found: $eventType");
        }

        $implements = class_implements($eventType);
        if (!isset($implements[EventInterface::class])) {
            throw new InvalidArgumentException(
                "Event class $eventType must implement EventInterface"
            );
        }

        $this->eventMap[$eventType] = true;
    }
}
