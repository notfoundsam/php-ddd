<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\EventSystem;

use SharedKernel\Domain\EventSystem\AbstractEvent;
use SharedKernel\Domain\EventSystem\AsyncEventInterface;

final class OrderShippedEvent extends AbstractEvent implements AsyncEventInterface
{
    private string $orderId;

    public function __construct(string $orderId)
    {
        parent::__construct();
        $this->orderId = $orderId;
    }

    public function serialize(): array
    {
        return ['order_id' => $this->orderId];
    }

    public static function fromPayload(array $payload): self
    {
        $event = new self($payload['order_id']);
        $event->id = $payload['id'];
        $event->version = $payload['version'];

        return $event;
    }
}
