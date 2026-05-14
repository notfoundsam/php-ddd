<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Channel;

use SharedKernel\Domain\Notification\Delivery\DeliveryReceipt;
use SharedKernel\Domain\Notification\Message\EmailMessage;

interface EmailNotifierInterface
{
    public function notify(EmailMessage $message): DeliveryReceipt;
}
