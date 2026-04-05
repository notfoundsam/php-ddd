<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\Aggregate\AggregateRoot;
use SharedKernel\Domain\EventSystem\DomainEventCollectorInterface;
use SharedKernel\Domain\EventSystem\OutboxEventInterface;

final class DomainEventCollector implements DomainEventCollectorInterface
{
    /** @var list<OutboxEventInterface> */
    private array $events = [];

    public function push(OutboxEventInterface $event): void
    {
        $this->events[] = $event;
    }

    public function collectFromAggregate(AggregateRoot $aggregate): void
    {
        foreach ($aggregate->releaseEvents() as $event) {
            $this->events[] = $event;
        }
    }

    /**
     * @return iterable<OutboxEventInterface>
     */
    public function pull(): iterable
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function hasEvents(): bool
    {
        return count($this->events) > 0;
    }

    public function getEventCount(): int
    {
        return count($this->events);
    }
}
