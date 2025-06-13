<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Infrastructure\Event;

use DateTimeImmutable;
use SharedKernel\Domain\Event\EventInterface;

class OtherTestEvent implements EventInterface
{
    public function getId(): string
    {
        return 'other-test-id';
    }

    public function getVersion(): int
    {
        return 1;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
