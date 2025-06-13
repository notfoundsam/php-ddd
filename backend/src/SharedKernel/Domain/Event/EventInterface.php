<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

use DateTimeImmutable;

interface EventInterface
{
    public function getId(): string;

    public function getVersion(): int;

    public function occurredOn(): DateTimeImmutable;
}
