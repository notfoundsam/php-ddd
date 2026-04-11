<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;

/**
 * @implements QueryInterface<TestQueryResponse>
 */
final class TestQuery implements QueryInterface
{
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
