<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;

final class SpyQueryBus implements QueryBusInterface
{
    public int $dispatched = 0;
    public ?QueryInterface $lastQuery = null;
    private QueryResponseInterface $response;

    public function __construct(?QueryResponseInterface $response = null)
    {
        $this->response = $response ?? new class implements QueryResponseInterface {
        };
    }

    public function dispatch(QueryInterface $query): QueryResponseInterface
    {
        $this->dispatched++;
        $this->lastQuery = $query;
        return $this->response;
    }
}
