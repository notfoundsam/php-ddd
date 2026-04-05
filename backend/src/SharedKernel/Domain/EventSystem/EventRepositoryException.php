<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when event repository operations fail
 *
 * Used by both outbox (database) and async (queue) repository implementations.
 */
class EventRepositoryException extends RuntimeException
{
    public static function failedToSerialize(string $eventType, string $eventId, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to serialize event: %s (ID: %s)', $eventType, $eventId),
            0,
            $previous
        );
    }

    public static function failedToStore(Throwable $previous): self
    {
        return new self(
            'Failed to store event',
            0,
            $previous
        );
    }

    public static function failedToStoreBatch(int $count, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to store batch of %d events', $count),
            0,
            $previous
        );
    }

    public static function failedToGetUnprocessedEvents(Throwable $previous): self
    {
        return new self(
            'Failed to retrieve unprocessed events',
            0,
            $previous
        );
    }

    public static function failedToMarkProcessed(string $eventId, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to mark event %s as processed', $eventId),
            0,
            $previous
        );
    }

    public static function failedToMarkFailed(string $eventId, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to mark event %s as failed', $eventId),
            0,
            $previous
        );
    }

    public static function failedToGetFailedEvents(Throwable $previous): self
    {
        return new self(
            'Failed to retrieve failed events',
            0,
            $previous
        );
    }

    public static function failedToGetEventById(string $eventId, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to retrieve event by ID: %s', $eventId),
            0,
            $previous
        );
    }

    public static function failedToCancel(string $eventId, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to cancel event %s', $eventId),
            0,
            $previous
        );
    }
}
