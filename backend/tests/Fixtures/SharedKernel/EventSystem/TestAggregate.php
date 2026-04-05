<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\EventSystem;

use SharedKernel\Domain\Aggregate\AggregateRoot;
use SharedKernel\Domain\EventSystem\OutboxEventInterface;

final class TestAggregate extends AggregateRoot
{
    public function recordEvent(OutboxEventInterface $event): void
    {
        $this->record($event);
    }
}
