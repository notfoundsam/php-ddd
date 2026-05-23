<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;

final class CommandBus implements CommandBusInterface
{
    private ContainerInterface $container;

    /** @var array<class-string<CommandInterface>, class-string> */
    private array $handlerClasses = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param class-string<CommandInterface> $commandClass
     * @param class-string $handlerClass
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlerClasses[$commandClass] = $handlerClass;
    }

    public function dispatch(CommandInterface $command): void
    {
        $commandClass = get_class($command);

        if (!isset($this->handlerClasses[$commandClass])) {
            throw HandlerNotFoundException::forCommand($commandClass);
        }

        $handlerClass = $this->handlerClasses[$commandClass];

        try {
            $handler = $this->container->get($handlerClass);
        } catch (NotFoundExceptionInterface $e) {
            throw HandlerNotResolvableException::forCommand($commandClass, $handlerClass, $e);
        }

        if (!is_callable($handler)) {
            throw HandlerNotCallableException::forCommand($commandClass, $handlerClass);
        }

        $handler($command);
    }
}
