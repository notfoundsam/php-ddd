<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\EventSystem;

use SharedKernel\Domain\EventSystem\AbstractEvent;
use SharedKernel\Domain\EventSystem\OutboxEventInterface;

final class VersionedEvent extends AbstractEvent implements OutboxEventInterface
{
    private string $name;
    private string $status;

    public function __construct(string $name, string $status = 'active')
    {
        parent::__construct();
        $this->name = $name;
        $this->status = $status;
    }

    public function serialize(): array
    {
        return ['name' => $this->name, 'status' => $this->status];
    }

    public static function fromPayload(array $payload): self
    {
        $event = new self($payload['name'], $payload['status'] ?? 'active');
        $event->id = $payload['id'];
        $event->version = $payload['version'];

        return $event;
    }

    public static function getCurrentVersion(): int
    {
        return 2;
    }

    public static function upcastToLatestVersion(array $payload, int $fromVersion): array
    {
        if ($fromVersion < 2) {
            $payload['status'] = 'active';
        }

        return $payload;
    }
}
