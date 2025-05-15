<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Event\EventInterface;
use SharedKernel\Domain\Event\EventListenerInterface;
use SharedKernel\Domain\Event\ListenerProviderInterface;

final class ListenerProvider implements ListenerProviderInterface
{
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
        $eventType = get_class($event);

        return $this->listeners[$eventType] ?? [];
    }
}
