<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Core interface for event repositories
 *
 * Defines the minimal contract for storing and processing events.
 * Used by AbstractEventProcessor during normal event processing.
 */
interface EventRepositoryInterface
{
    /**
     * Stores an event for processing
     *
     * @param EventInterface $event The event to store
     * @throws EventRepositoryException
     */
    public function store(EventInterface $event): void;

    /**
     * Stores multiple events in a single operation
     *
     * @param iterable<EventInterface> $events The events to store
     * @throws EventRepositoryException
     */
    public function storeBatch(iterable $events): void;

    /**
     * Retrieves unprocessed events as a batch
     *
     * Database implementations use pessimistic locking (FOR UPDATE SKIP LOCKED)
     * to prevent concurrent processing by multiple workers.
     *
     * @param int|null $limit Maximum number of events to retrieve (null uses repository default)
     * @return iterable<ReceivedEvent>
     * @throws EventRepositoryException
     */
    public function getUnprocessedEvents(?int $limit = null): iterable;

    /**
     * Marks an event as successfully processed
     *
     * @param ReceivedEvent $receivedEvent The processed event with storage context
     * @throws EventRepositoryException
     */
    public function markProcessed(ReceivedEvent $receivedEvent): void;

    /**
     * Marks an event as failed during processing
     *
     * Behavior is implementation-specific:
     * - Database: retry with exponential backoff, then move to DLQ
     * - Queue: a message becomes visible again after visibility timeout
     *
     * @param ReceivedEvent $receivedEvent The failed event with storage context
     * @param string|null $errorMessage Optional error message
     * @param array<string, mixed>|null $errorContext Optional error context
     * @throws EventRepositoryException
     */
    public function markFailed(
        ReceivedEvent $receivedEvent,
        ?string $errorMessage = null,
        ?array $errorContext = null
    ): void;
}
