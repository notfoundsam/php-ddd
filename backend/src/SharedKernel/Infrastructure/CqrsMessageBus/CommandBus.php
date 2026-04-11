<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;

final class CommandBus implements CommandBusInterface
{
    /**
     * @var array<string, callable>
     */
    private array $handlers = [];

    public function register(string $commandClass, callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    public function dispatch(CommandInterface $command): void
    {
        $commandClass = get_class($command);

        if (!isset($this->handlers[$commandClass])) {
            throw HandlerNotFoundException::forCommand($commandClass);
        }

        ($this->handlers[$commandClass])($command);
    }
}
