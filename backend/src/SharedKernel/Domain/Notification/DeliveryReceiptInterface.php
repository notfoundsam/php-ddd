<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification;

interface DeliveryReceiptInterface
{
    public function getValue(): ?string;

    public function isEmpty(): bool;
}
