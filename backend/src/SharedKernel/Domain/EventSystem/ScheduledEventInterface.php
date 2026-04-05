<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

use DateTimeImmutable;

/**
 * Marker interface for events that should be processed at a scheduled time
 *
 * Events implementing this interface will be stored in the database and processed
 * by a background worker when the scheduled time arrives. Unlike immediate async events,
 * these support delayed delivery and cancellation.
 *
 * Use Cases:
 * - Reminder notifications (e.g., "estimate expires in 24 hours")
 * - Delayed follow-ups (e.g., "send email 15 minutes after signup")
 * - Cancellable notifications (e.g., "cancel if user takes action")
 * - Time-based campaigns
 *
 * Stored in a database table with a scheduled_for timestamp.
 * A dedicated background worker poll for events whose scheduled time has passed.
 * Supports cancellation before processing and retry with DLQ on failure.
 */
interface ScheduledEventInterface extends EventInterface
{
    /**
     * Returns the timestamp when this event should be processed
     *
     * The worker will not process this event until this time has passed.
     * Events are processed in chronological order (earliest first).
     *
     * @return DateTimeImmutable When to process this event
     */
    public function getScheduledFor(): DateTimeImmutable;
}
