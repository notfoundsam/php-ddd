<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification;

interface NotifierInterface
{
    public function notify(NotificationMessageInterface $message): DeliveryReceiptInterface;
}
