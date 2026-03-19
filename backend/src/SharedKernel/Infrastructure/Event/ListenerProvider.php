<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Event\EventInterface;
use SharedKernel\Domain\Event\EventListenerInterface;
use SharedKernel\Domain\Event\ListenerProviderInterface;

final class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, array<EventListenerInterface>> */
    private array $listeners = [];

    public function addListener(string $eventClassName, EventListenerInterface $listener): void
    {
        if (!isset($this->listeners[$eventClassName])) {
            $this->listeners[$eventClassName] = [];
        }

        $this->listeners[$eventClassName][] = $listener;
    }

    public function getListenersForEvent(EventInterface $event): iterable
    {
        $listeners = [];
        $eventClasses = array_merge(
            class_parents($event) ?: [],
            class_implements($event) ?: [],
            [get_class($event)]
        );

        foreach ($eventClasses as $eventClass) {
            if (!isset($this->listeners[$eventClass])) {
                continue;
            }

            $listeners = array_merge($listeners, $this->listeners[$eventClass]);
        }

        return $listeners;
    }
}
