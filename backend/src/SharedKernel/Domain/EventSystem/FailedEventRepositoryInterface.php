<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Interface for managing failed events (Dead Letter Queue operations)
 *
 * Implemented by database-backed repositories that support event lookup,
 * retry, and manual resolution. Not applicable to queue-based repositories
 * where DLQ is managed by the queue infrastructure.
 */
interface FailedEventRepositoryInterface
{
    /**
     * Retrieves permanently failed events
     *
     * @param int|null $limit Maximum number of events to retrieve
     * @return iterable
     * @throws EventRepositoryException
     */
    public function getFailedEvents(?int $limit = null): iterable;

    /**
     * Manually resets a failed event for retry
     *
     * @param string $eventId The event ID to reset
     * @throws EventRepositoryException
     */
    public function resetForRetry(string $eventId): void;

    /**
     * Manually marks an event as resolved
     *
     * @param string $eventId The event ID to resolve
     * @param string $resolvedBy Who resolved the event
     * @throws EventRepositoryException
     */
    public function markAsResolvedManually(string $eventId, string $resolvedBy): void;

    /**
     * Retrieves an event by ID
     *
     * @param string $eventId The event ID
     * @return EventInterface|null The event or null if not found
     * @throws EventRepositoryException
     * @throws EventDeserializationException
     */
    public function getEventById(string $eventId): ?EventInterface;
}
