<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification;

interface NotificationMessageInterface
{
    public function getContent(): string;

    public function getMeta(): array;
}
