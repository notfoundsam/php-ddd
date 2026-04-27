<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;

/**
 * @implements QueryInterface<TestQueryResponse>
 */
final class SecondTestQuery implements QueryInterface
{
}
