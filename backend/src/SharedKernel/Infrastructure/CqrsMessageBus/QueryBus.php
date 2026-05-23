<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

use Psr\Container\ContainerInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;

final class QueryBus implements QueryBusInterface
{
    private ContainerInterface $container;

    /** @var array<class-string, class-string> */
    private array $handlerClasses = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param class-string $queryClass
     * @param class-string $handlerClass
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlerClasses[$queryClass] = $handlerClass;
    }

    /**
     * @template TResponse of QueryResponseInterface
     * @param QueryInterface<TResponse> $query
     * @return TResponse
     */
    public function dispatch(QueryInterface $query): QueryResponseInterface
    {
        $queryClass = get_class($query);

        if (!isset($this->handlerClasses[$queryClass])) {
            throw HandlerNotFoundException::forQuery($queryClass);
        }

        $handler = $this->container->get($this->handlerClasses[$queryClass]);
        return $handler($query);
    }
}
