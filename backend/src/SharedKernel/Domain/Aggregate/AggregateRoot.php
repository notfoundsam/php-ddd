<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Aggregate;

use SharedKernel\Domain\Event\EventInterface;

abstract class AggregateRoot
{
    /** @var array<EventInterface> */
    private array $recordedEvents = [];

    protected function record(EventInterface $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return iterable<EventInterface>
     */
    public function releaseEvents(): iterable
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
