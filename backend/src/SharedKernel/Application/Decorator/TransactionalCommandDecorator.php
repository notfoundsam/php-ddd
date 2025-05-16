<?php

declare(strict_types=1);

namespace SharedKernel\Application\Decorator;

use SharedKernel\Application\Command\CommandHandlerInterface;
use SharedKernel\Application\Command\CommandInterface;
use SharedKernel\Application\Transaction\TransactionManagerInterface;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\EventDispatcherInterface;
use SharedKernel\Domain\Event\EventManagerInterface;
use Throwable;

class TransactionalCommandDecorator implements CommandHandlerInterface
{
    private CommandHandlerInterface $next;

    private TransactionManagerInterface $transactionManager;

    private EventManagerInterface $eventManager;

    private EventDispatcherInterface $eventDispatcher;

    private EventBusInterface $eventBus;

    public function __construct(
        CommandHandlerInterface $next,
        TransactionManagerInterface $transactionManager,
        EventManagerInterface $eventManager,
        EventDispatcherInterface $eventDispatcher,
        EventBusInterface $eventBus
    ) {
        $this->next = $next;
        $this->transactionManager = $transactionManager;
        $this->eventManager = $eventManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventBus = $eventBus;
    }

    /**
     * @throws Throwable
     */
    public function handle(CommandInterface $command): void
    {
        $this->transactionManager->begin();

        try {
            $this->next->handle($command);
            $this->eventDispatcher->dispatchAll($this->eventManager->pullSynchronousEvents());
            $this->eventBus->publishAll($this->eventManager->pullAsynchronousEvents());
            $this->transactionManager->commit();
        } catch (Throwable $e) {
            $this->transactionManager->rollback();
            throw $e;
        }
    }
}
