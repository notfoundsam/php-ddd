<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

interface EventBusInterface
{
    public function publish(PostCommitEventInterface ...$events): void;
}
