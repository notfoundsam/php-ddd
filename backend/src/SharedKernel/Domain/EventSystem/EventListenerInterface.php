<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

interface EventListenerInterface
{
    public function handle(EventInterface $event): void;
}
