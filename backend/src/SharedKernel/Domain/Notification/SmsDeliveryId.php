<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification;

final class SmsDeliveryId implements DeliveryReceiptInterface
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return empty($this->value);
    }
}
