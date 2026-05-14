<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Exception;

use RuntimeException;
use Throwable;

class NotificationDeliveryException extends RuntimeException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function providerError(string $provider, string $details): self
    {
        return new self("$provider delivery failed: $details");
    }
}
