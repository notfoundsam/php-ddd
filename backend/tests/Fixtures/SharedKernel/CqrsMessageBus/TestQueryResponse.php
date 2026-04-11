<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;

final class TestQueryResponse implements QueryResponseInterface
{
    private string $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function getData(): string
    {
        return $this->data;
    }
}
