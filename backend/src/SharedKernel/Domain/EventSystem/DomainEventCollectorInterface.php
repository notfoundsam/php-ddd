<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

use SharedKernel\Domain\Aggregate\AggregateRoot;

interface DomainEventCollectorInterface
{
    /**
     * Pushes a domain event into the collection
     */
    public function push(OutboxEventInterface $event): void;

    /**
     * Collects domain events from an aggregate root
     */
    public function collectFromAggregate(AggregateRoot $aggregate): void;

    /**
     * Returns and clears all collected domain events
     *
     * @return iterable<OutboxEventInterface>
     */
    public function pull(): iterable;

    /**
     * Returns true if there are any domain events waiting
     */
    public function hasEvents(): bool;

    /**
     * Returns the count of collected domain events
     */
    public function getEventCount(): int;
}
