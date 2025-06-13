<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Persistence;

use SharedKernel\Domain\Event\OutboxEventInterface;
use SharedKernel\Domain\Repository\OutboxRepositoryInterface;

class FuelOutboxRepository implements OutboxRepositoryInterface
{
    public function save(OutboxEventInterface ...$events): void
    {
        foreach ($events as $event) {
            $this->saveEvent($event);
        }
    }

    private function saveEvent(OutboxEventInterface $event): void
    {
        // @todo: Implement the logic to save the event to the database or any other storage.
    }
}
