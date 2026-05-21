<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\EventSystem\EventRepositoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\EventSystem\OutboxEventProcessorInterface;
use SharedKernel\Domain\Logger\LoggerInterface;

final class OutboxEventProcessor extends AbstractEventProcessor implements OutboxEventProcessorInterface
{
    private EventRepositoryInterface $events;

    public function __construct(
        EventRepositoryInterface $events,
        ListenerProviderInterface $listenerProvider,
        LoggerInterface $logger
    ) {
        parent::__construct($listenerProvider, $logger);
        $this->events = $events;
    }

    protected function getRepository(): EventRepositoryInterface
    {
        return $this->events;
    }

    protected function getEventTypeLabel(): string
    {
        return 'Outbox';
    }
}
