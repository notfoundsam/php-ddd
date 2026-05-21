<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
use SharedKernel\Domain\EventSystem\AsyncRepositoryInterface;
use SharedKernel\Domain\EventSystem\EventRepositoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\Logger\LoggerInterface;

/**
 * Async event processor
 *
 * Works with any AsyncRepositoryInterface implementation (SQS, in-memory, etc.).
 */
final class AsyncEventProcessor extends AbstractEventProcessor implements AsyncEventProcessorInterface
{
    private AsyncRepositoryInterface $events;

    public function __construct(
        AsyncRepositoryInterface $events,
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
        return 'Async';
    }
}
