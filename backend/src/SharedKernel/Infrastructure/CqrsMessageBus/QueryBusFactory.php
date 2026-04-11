<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

use Psr\Container\ContainerInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;

final class QueryBusFactory
{
    private ContainerInterface $container;
    /** @var QueryHandlerRegistryInterface[] */
    private array $registries;

    /**
     * @param QueryHandlerRegistryInterface[] $registries
     */
    public function __construct(ContainerInterface $container, array $registries)
    {
        $this->container = $container;
        $this->registries = $registries;
    }

    public function __invoke(): QueryBusInterface
    {
        $bus = new QueryBus();

        foreach ($this->registries as $registry) {
            foreach ($registry->getQueryHandlers() as $queryClass => $handlerClass) {
                $bus->register($queryClass, $this->container->get($handlerClass));
            }
        }

        return $bus;
    }
}
