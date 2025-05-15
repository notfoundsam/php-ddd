<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

use DateTimeImmutable;

interface EventInterface
{
    public function occurredOn(): DateTimeImmutable;
}
