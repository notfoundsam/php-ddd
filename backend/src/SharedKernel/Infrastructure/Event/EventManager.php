<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Aggregate\AggregateRoot;
use SharedKernel\Domain\Event\AsynchronousEventInterface;
use SharedKernel\Domain\Event\EventInterface;
use SharedKernel\Domain\Event\EventManagerInterface;
use SharedKernel\Domain\Event\SynchronousEventInterface;

final class EventManager implements EventManagerInterface
{
    /**
     * @var array<EventInterface>
     */
    private array $syncEvents = [];

    /**
     * @var array<EventInterface>
     */
    private array $asyncEvents = [];

    public function collectFromAggregate(AggregateRoot $aggregate): void
    {
        foreach ($aggregate->releaseEvents() as $event) {
            $this->push($event);
        }
    }

    public function push(EventInterface $event): void
    {
        if ($event instanceof SynchronousEventInterface) {
            $this->syncEvents[] = $event;
        } elseif ($event instanceof AsynchronousEventInterface) {
            $this->asyncEvents[] = $event;
        } else {
            throw new \LogicException('Unclassified event: ' . get_class($event));
        }
    }

    public function pullSynchronousEvents(): iterable
    {
        $events = $this->syncEvents;
        $this->syncEvents = [];

        return $events;
    }

    public function pullAsynchronousEvents(): iterable
    {
        $events = $this->asyncEvents;
        $this->asyncEvents = [];

        return $events;
    }
}
