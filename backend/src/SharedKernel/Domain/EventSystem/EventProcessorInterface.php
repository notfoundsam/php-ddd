<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

use Throwable;

/**
 * Base interface for event processors
 *
 * Provides a unified contract for storing and processing events,
 * regardless of the underlying storage mechanism (database, queue, etc.).
 */
interface EventProcessorInterface
{
    /**
     * Stores a single event
     *
     * @param EventInterface $event The event to store
     * @throws Throwable
     */
    public function store(EventInterface $event): void;

    /**
     * Stores multiple events in a single operation
     *
     * @param iterable<EventInterface> $events The events to store
     * @throws Throwable
     */
    public function storeBatch(iterable $events): void;

    /**
     * Retrieves and processes unprocessed events from storage
     *
     * Called in a loop by background workers. Each call fetches one batch,
     * dispatches events to listeners, and marks them as processed or failed.
     *
     * @return int Number of events retrieved (regardless of listener success/failure)
     * @throws EventRepositoryException If the repository fetch fails
     */
    public function processEvents(): int;
}
