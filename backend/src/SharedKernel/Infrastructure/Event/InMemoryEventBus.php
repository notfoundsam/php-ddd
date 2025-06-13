<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\ListenerProviderInterface;
use SharedKernel\Domain\Event\PostCommitEventInterface;

final class InMemoryEventBus implements EventBusInterface
{
    private ListenerProviderInterface $listenerProvider;

    public function __construct(ListenerProviderInterface $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }

    public function publish(PostCommitEventInterface ...$events): void
    {
        foreach ($events as $event) {
            $this->publishEvent($event);
        }
    }

    public function publishEvent(PostCommitEventInterface $event): void
    {
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
    }
}
