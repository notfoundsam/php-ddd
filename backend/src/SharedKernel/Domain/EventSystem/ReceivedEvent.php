<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Value object representing a received event with storage-specific context
 *
 * Wraps a domain event with metadata needed for acknowledgment and storage operations.
 * The storage context can be:
 * - SQS: receipt handle (ephemeral, changes each receive)
 * - Outbox: event ID (persistent, from database)
 * - Other storages: implementation-specific metadata
 */
final class ReceivedEvent
{
    private EventInterface $event;
    /** @var mixed */
    private $storageContext;

    /**
     * @param EventInterface $event
     * @param mixed $storageContext Storage-specific context (SQS receipt handle, database event ID, etc.)
     */
    public function __construct(EventInterface $event, $storageContext)
    {
        $this->event = $event;
        $this->storageContext = $storageContext;
    }

    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    /**
     * Gets storage-specific context needed for acknowledgment
     *
     * @return mixed Storage context (e.g., SQS receipt handle, database event ID)
     */
    public function getStorageContext()
    {
        return $this->storageContext;
    }
}
