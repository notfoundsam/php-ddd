<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

interface EventDispatcherInterface
{
    public function dispatch(EventInterface $event): void;

    /**
     * @param iterable<EventInterface> $events
     */
    public function dispatchAll(iterable $events): void;
}
