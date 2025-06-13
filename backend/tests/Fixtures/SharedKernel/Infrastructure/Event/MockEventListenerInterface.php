<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Event\EventListenerInterface;
use SharedKernel\Domain\Event\TransactionalEventInterface;

interface MockEventListenerInterface extends EventListenerInterface
{
    public function __invoke(TransactionalEventInterface $event): void;
}
