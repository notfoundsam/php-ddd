<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Infrastructure\Event;

use SharedKernel\Domain\Event\EventInterface;
use SharedKernel\Domain\Event\EventListenerInterface;

interface MockEventListenerInterface extends EventListenerInterface
{
    public function __invoke(EventInterface $event): void;
}
