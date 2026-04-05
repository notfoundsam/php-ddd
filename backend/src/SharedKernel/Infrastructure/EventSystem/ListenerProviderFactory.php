<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use Psr\Container\ContainerInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;

final class ListenerProviderFactory
{
    private ContainerInterface $container;
    private EventListenerRegistry $registry;

    public function __construct(ContainerInterface $container, EventListenerRegistry $registry)
    {
        $this->container = $container;
        $this->registry = $registry;
    }

    public function __invoke(): ListenerProviderInterface
    {
        $provider = new ListenerProvider();
        $listeners = $this->registry->getEventListeners();

        foreach ($listeners as $eventClass => $listenerClasses) {
            foreach ((array)$listenerClasses as $listenerClass) {
                $listener = $this->container->get($listenerClass);
                $provider->addListener($eventClass, $listener);
            }
        }

        return $provider;
    }
}
