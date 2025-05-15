<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

interface ListenerProviderInterface
{
    public function addListener(string $eventClassName, EventListenerInterface $listener): void;

    /**
     * @return iterable<EventListenerInterface>
     */
    public function getListenersForEvent(EventInterface $event): iterable;
}
