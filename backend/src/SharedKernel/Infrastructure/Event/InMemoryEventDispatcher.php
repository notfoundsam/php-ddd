<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Event\EventDispatcherInterface;
use SharedKernel\Domain\Event\ListenerProviderInterface;
use SharedKernel\Domain\Event\TransactionalEventInterface;

final class InMemoryEventDispatcher implements EventDispatcherInterface
{
    private ListenerProviderInterface $listenerProvider;

    public function __construct(ListenerProviderInterface $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }

    public function dispatch(TransactionalEventInterface ...$events): void
    {
        foreach ($events as $event) {
            $this->dispatchEvent($event);
        }
    }

    private function dispatchEvent(TransactionalEventInterface $event): void
    {
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
    }
}
