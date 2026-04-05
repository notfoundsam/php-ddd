<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

interface ListenerProviderInterface
{
    /**
     * Registers a listener for a specific event class
     *
     * @param string $eventClass The FQCN of the event class
     * @param EventListenerInterface $listener The listener to register
     */
    public function addListener(string $eventClass, EventListenerInterface $listener): void;

    /**
     * Returns all listeners for the given event
     *
     * Supports inheritance — returns listeners registered for parent classes
     * and interfaces. Listeners are returned in registration order.
     *
     * @param EventInterface $event The event to find listeners for
     * @return iterable<EventListenerInterface>
     */
    public function getListenersForEvent(EventInterface $event): iterable;
}
