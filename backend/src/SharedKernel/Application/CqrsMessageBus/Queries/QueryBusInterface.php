<?php

declare(strict_types=1);

namespace SharedKernel\Application\CqrsMessageBus\Queries;

interface QueryBusInterface
{
    /**
     * @template TResponse of QueryResponseInterface
     * @param QueryInterface<TResponse> $query
     * @return TResponse
     */
    public function dispatch(QueryInterface $query): QueryResponseInterface;
}
