<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

use Psr\Container\ContainerInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;

final class CommandBusFactory
{
    private ContainerInterface $container;
    /** @var CommandHandlerRegistryInterface[] */
    private array $registries;

    /**
     * @param CommandHandlerRegistryInterface[] $registries
     */
    public function __construct(ContainerInterface $container, array $registries)
    {
        $this->container = $container;
        $this->registries = $registries;
    }

    public function __invoke(): CommandBusInterface
    {
        $bus = new CommandBus($this->container);

        foreach ($this->registries as $registry) {
            foreach ($registry->getCommandHandlers() as $commandClass => $handlerClass) {
                $bus->register($commandClass, $handlerClass);
            }
        }

        return $bus;
    }
}
