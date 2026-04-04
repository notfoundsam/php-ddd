<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Aggregate;

use SharedKernel\Domain\EventSystem\OutboxEventInterface;

abstract class AggregateRoot
{
    /** @var array<OutboxEventInterface> */
    private array $recordedEvents = [];

    protected function record(OutboxEventInterface $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return iterable<OutboxEventInterface>
     */
    public function releaseEvents(): iterable
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
