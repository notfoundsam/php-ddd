<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Channel;

use SharedKernel\Domain\Notification\Delivery\DeliveryReceipt;
use SharedKernel\Domain\Notification\Message\SmsMessage;

interface SmsNotifierInterface
{
    public function notify(SmsMessage $message): DeliveryReceipt;
}
