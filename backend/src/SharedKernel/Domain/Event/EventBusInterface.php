<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

interface EventBusInterface
{
    public function publish(EventInterface $event): void;

    /**
     * @param iterable<EventInterface> $events
     */
    public function publishAll(iterable $events): void;
}
