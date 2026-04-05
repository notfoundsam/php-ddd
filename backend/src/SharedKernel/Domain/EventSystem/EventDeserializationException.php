<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

use Exception;
use Throwable;

class EventDeserializationException extends Exception
{
    public static function unknownEventType(string $eventType): self
    {
        return new self("Unknown event type: $eventType");
    }

    public static function eventClassNotFound(string $eventType): self
    {
        return new self("Event class not found: $eventType");
    }

    public static function failedToDecodePayload(string $eventId): self
    {
        return new self("Failed to decode event payload for event_id: $eventId");
    }

    public static function failedToDeserialize(string $eventType, Throwable $previous): self
    {
        return new self(
            "Failed to deserialize event $eventType: " . $previous->getMessage(),
            0,
            $previous
        );
    }
}
