<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Aggregate\AggregateRoot;
use SharedKernel\Domain\Event\OutboxEventInterface;
use SharedKernel\Domain\Event\PostCommitEventInterface;
use SharedKernel\Domain\Event\EventInterface;
use SharedKernel\Domain\Event\EventManagerInterface;
use SharedKernel\Domain\Event\TransactionalEventInterface;

final class EventManager implements EventManagerInterface
{
    /**
     * @var iterable<EventInterface>
     */
    private iterable $syncEvents = [];

    /**
     * @var iterable<EventInterface>
     */
    private iterable $asyncEvents = [];

    /**
     * @var iterable<OutboxEventInterface>
     */
    private iterable $outboxEvents = [];

    public function collectFromAggregate(AggregateRoot $aggregate): void
    {
        foreach ($aggregate->releaseEvents() as $event) {
            $this->push($event);
        }
    }

    public function push(EventInterface $event): void
    {
        if ($event instanceof TransactionalEventInterface) {
            $this->syncEvents[] = $event;
        } elseif ($event instanceof PostCommitEventInterface) {
            $this->asyncEvents[] = $event;
        } elseif ($event instanceof OutboxEventInterface) {
            $this->outboxEvents[] = $event;
        } else {
            throw new \LogicException('Unclassified event: ' . get_class($event));
        }
    }

    public function pullTransactionalEvents(): iterable
    {
        $events = $this->syncEvents;
        $this->syncEvents = [];

        return $events;
    }

    public function pullPostCommitEvents(): iterable
    {
        $events = $this->asyncEvents;
        $this->asyncEvents = [];

        return $events;
    }

    public function pullOutboxEvents(): iterable
    {
        $events = $this->outboxEvents;
        $this->outboxEvents = [];

        return $events;
    }
}
