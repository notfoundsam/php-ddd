<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Notification\Delivery;

use SharedKernel\Domain\Notification\Delivery\DeliveryReceipt;
use PHPUnit\Framework\TestCase;

class DeliveryReceiptTest extends TestCase
{
    public function testConstructorStoresProviderMessageId(): void
    {
        $receipt = new DeliveryReceipt('ses-message-id-123');

        $this->assertSame('ses-message-id-123', $receipt->getProviderMessageId());
    }

    public function testConstructorAcceptsNullProviderMessageId(): void
    {
        $receipt = new DeliveryReceipt(null);

        $this->assertNull($receipt->getProviderMessageId());
    }
}
