<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\EventSystem\EventInterface;
use SharedKernel\Domain\EventSystem\EventListenerInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;

class ListenerProvider implements ListenerProviderInterface
{
    /**
     * @var array<string, list<EventListenerInterface>>
     */
    private array $listeners = [];

    /**
     * @var array<string, list<EventListenerInterface>>
     */
    private array $listenerCache = [];

    public function addListener(string $eventClass, EventListenerInterface $listener): void
    {
        if (!isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        $this->listeners[$eventClass][] = $listener;

        $this->listenerCache = [];
    }

    public function getListenersForEvent(EventInterface $event): iterable
    {
        $eventClass = get_class($event);

        if (!isset($this->listenerCache[$eventClass])) {
            $this->listenerCache[$eventClass] = $this->buildListenerList($event);
        }

        return $this->listenerCache[$eventClass];
    }

    /**
     * @param EventInterface $event
     * @return array<EventListenerInterface>
     */
    private function buildListenerList(EventInterface $event): array
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
