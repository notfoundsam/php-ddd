<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\EventSystem\EventRepositoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\EventSystem\ScheduledEventProcessorInterface;
use SharedKernel\Domain\Logger\LoggerInterface;

/**
 * Scheduled event processor
 *
 * Processes events at their scheduled time using database storage
 * with support for delayed delivery and cancellation.
 *
 * Reuses all retry, DLQ, metrics, and logging logic from AbstractEventProcessor.
 */
final class ScheduledEventProcessor extends AbstractEventProcessor implements ScheduledEventProcessorInterface
{
    private EventRepositoryInterface $scheduledEventRepository;

    public function __construct(
        EventRepositoryInterface $scheduledEventRepository,
        ListenerProviderInterface $listenerProvider,
        LoggerInterface $logger
    ) {
        parent::__construct($listenerProvider, $logger);
        $this->scheduledEventRepository = $scheduledEventRepository;
    }

    protected function getRepository(): EventRepositoryInterface
    {
        return $this->scheduledEventRepository;
    }

    protected function getEventTypeLabel(): string
    {
        return 'Scheduled';
    }
}
