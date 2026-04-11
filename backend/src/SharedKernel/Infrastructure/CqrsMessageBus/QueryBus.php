<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;

final class QueryBus implements QueryBusInterface
{
    /**
     * @var array<string, callable>
     */
    private array $handlers = [];

    public function register(string $queryClass, callable $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    /**
     * @template TResponse of QueryResponseInterface
     * @param QueryInterface<TResponse> $query
     * @return TResponse
     */
    public function dispatch(QueryInterface $query): QueryResponseInterface
    {
        $queryClass = get_class($query);

        if (!isset($this->handlers[$queryClass])) {
            throw HandlerNotFoundException::forQuery($queryClass);
        }

        return ($this->handlers[$queryClass])($query);
    }
}
