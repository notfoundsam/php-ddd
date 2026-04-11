<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryHandlerInterface;

final class TestQueryHandler implements QueryHandlerInterface
{
    public function __invoke(TestQuery $query): TestQueryResponse
    {
        return new TestQueryResponse('result-for-' . $query->getId());
    }
}
