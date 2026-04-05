<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\EventSystem\EventRepositoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\EventSystem\OutboxEventProcessorInterface;
use SharedKernel\Domain\Logger\LoggerInterface;

final class OutboxEventProcessor extends AbstractEventProcessor implements OutboxEventProcessorInterface
{
    private EventRepositoryInterface $repository;

    public function __construct(
        EventRepositoryInterface $repository,
        ListenerProviderInterface $listenerProvider,
        LoggerInterface $logger
    ) {
        parent::__construct($listenerProvider, $logger);
        $this->repository = $repository;
    }

    protected function getRepository(): EventRepositoryInterface
    {
        return $this->repository;
    }

    protected function getEventTypeLabel(): string
    {
        return 'Outbox';
    }
}
