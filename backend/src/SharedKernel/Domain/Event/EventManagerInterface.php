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
    public function pullSynchronousEvents(): iterable;

    /**
     * @return iterable<EventInterface>
     */
    public function pullAsynchronousEvents(): iterable;
}
