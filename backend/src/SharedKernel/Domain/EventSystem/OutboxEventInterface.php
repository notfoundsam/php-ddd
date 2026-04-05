<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Marker interface for domain events stored via the transactional outbox pattern
 *
 * Events implementing this interface are recorded by aggregates and stored
 * in the outbox table within the same database transaction as state changes.
 */
interface OutboxEventInterface extends EventInterface
{
}
