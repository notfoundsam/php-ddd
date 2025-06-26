<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification;

final class NullDeliveryId implements DeliveryReceiptInterface
{
    public function getValue(): ?string
    {
        return null;
    }

    public function isEmpty(): bool
    {
        return true;
    }
}
