<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Aggregate\AggregateRoot;
use SharedKernel\Domain\Event\EventInterface;
use SharedKernel\Domain\Event\EventManagerInterface;

final class EventManager implements EventManagerInterface
{
    /**
     * @var array<EventInterface>
     */
    private array $events = [];

    public function collectFrom(AggregateRoot $aggregate): void
    {
        foreach ($aggregate->releaseEvents() as $event) {
            $this->events[] = $event;
        }
    }

    public function releaseAll(): iterable
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
