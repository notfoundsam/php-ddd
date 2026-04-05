<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

use DateTimeImmutable;

/**
 * Base interface for all domain events
 *
 * IMPORTANT: Events must extend AbstractEvent, not implement this interface directly.
 * AbstractEvent provides required static factory methods (fromPayload, getCurrentVersion,
 * upcastToLatestVersion) that cannot be enforced by PHP interfaces but are essential
 * for event deserialization and versioning.
 *
 * EVENT LIFECYCLE AND DELETION:
 * - Event classes should NEVER be deleted, even if obsolete
 * - Events may exist in storage (outbox_events table, SQS queues) for hours/days
 * - Deleting a class breaks deserialization, sending events to DLQ
 * - For refactoring: Keep old class as "tombstone", use upcastToLatestVersion() to migrate
 * - For removal: Deprecate first, wait for all events to process, then remove
 *
 * @see AbstractEvent
 */
interface EventInterface
{
    /**
     * Returns the unique identifier for this event instance
     */
    public function getId(): string;

    /**
     * Returns the schema version of this event
     *
     * This reflects the current class version and is used to determine
     * if upcasting is needed during deserialization.
     */
    public function getVersion(): int;

    /**
     * Returns the timestamp when this event occurred
     */
    public function getOccurredAt(): DateTimeImmutable;

    /**
     * Returns the correlation ID shared across an entire event chain
     *
     * The first event in a chain uses its own ID as the correlation ID.
     * All downstream events inherit this value to enable end-to-end tracing.
     */
    public function getCorrelationId(): string;

    /**
     * Returns the ID of the event that directly caused this one
     *
     * Null for the first event in a chain (e.g., a domain event triggered by a command).
     */
    public function getCausationId(): ?string;

    /**
     * Serializes event to array for storage
     *
     * Should return only domain data (not metadata like id, version, occurred_at).
     * Repositories inject metadata during serialization/deserialization.
     *
     * @return array<array-key, mixed>
     */
    public function serialize(): array;
}
