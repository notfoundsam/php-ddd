<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Domain\EventSystem\DomainEventCollectorInterface;
use SharedKernel\Domain\EventSystem\OutboxEventProcessorInterface;
use SharedKernel\Application\CqrsMessageBus\TransactionManagerInterface;
use Throwable;

final class CommandTransactionDecorator implements CommandBusInterface
{
    private CommandBusInterface $inner;
    private DomainEventCollectorInterface $domainEventCollector;
    private TransactionManagerInterface $transactionManager;
    private OutboxEventProcessorInterface $outboxProcessor;

    public function __construct(
        CommandBusInterface $inner,
        DomainEventCollectorInterface $domainEventCollector,
        TransactionManagerInterface $transactionManager,
        OutboxEventProcessorInterface $outboxProcessor
    ) {
        $this->inner = $inner;
        $this->domainEventCollector = $domainEventCollector;
        $this->transactionManager = $transactionManager;
        $this->outboxProcessor = $outboxProcessor;
    }

    /**
     * @throws Throwable
     */
    public function dispatch(CommandInterface $command): void
    {
        $this->transactionManager->beginTransaction();

        try {
            $this->inner->dispatch($command);
            $this->outboxProcessor->storeBatch($this->domainEventCollector->pull());
            $this->transactionManager->commit();
        } catch (Throwable $e) {
            $this->domainEventCollector->pull();
            $this->transactionManager->rollback();
            throw $e;
        }
    }
}
