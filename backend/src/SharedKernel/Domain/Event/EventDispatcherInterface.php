<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

interface EventDispatcherInterface
{
    public function dispatch(TransactionalEventInterface ...$events): void;
}
