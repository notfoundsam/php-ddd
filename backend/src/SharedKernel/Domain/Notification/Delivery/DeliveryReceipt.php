<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Delivery;

final class DeliveryReceipt
{
    private ?string $providerMessageId;

    public function __construct(?string $providerMessageId)
    {
        $this->providerMessageId = $providerMessageId;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }
}
