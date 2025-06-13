<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

use SharedKernel\Domain\Aggregate\AggregateRoot;

interface EventManagerInterface
{
    public function collectFromAggregate(AggregateRoot $aggregate): void;

    public function push(EventInterface $event): void;

    /**
     * @return iterable<EventInterface>
     */
    public function pullTransactionalEvents(): iterable;

    /**
     * @return iterable<EventInterface>
     */
    public function pullPostCommitEvents(): iterable;

    /**
     * @return iterable<OutboxEventInterface>
     */
    public function pullOutboxEvents(): iterable;
}
