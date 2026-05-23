<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

/**
 * Mutable event log shared between fixtures. Lets tests assert ordering across
 * fixture boundaries (e.g. "container resolved AFTER transaction began") without
 * passing arrays by reference, which PHPStan can't trace through properties.
 */
final class TestLog
{
    /** @var list<string> */
    private array $events = [];

    public function append(string $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->events;
    }
}
