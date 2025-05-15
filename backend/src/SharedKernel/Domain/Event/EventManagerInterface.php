<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

use SharedKernel\Domain\Aggregate\AggregateRoot;

interface EventManagerInterface
{
    public function collectFrom(AggregateRoot $aggregate): void;
    public function releaseAll(): iterable;
}
