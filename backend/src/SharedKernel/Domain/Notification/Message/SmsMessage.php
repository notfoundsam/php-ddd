<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Message;

use SharedKernel\Domain\Notification\Exception\InvalidSmsMessageException;
use SharedKernel\Domain\ValueObjects\PhoneNumber;

final class SmsMessage
{
    private PhoneNumber $phoneNumber;
    private string $message;
    private bool $statusCallbackEnabled;

    private function __construct(PhoneNumber $phoneNumber, string $message)
    {
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
        $this->statusCallbackEnabled = false;
    }

    public static function create(PhoneNumber $phoneNumber, string $message): self
    {
        if (!$phoneNumber->isMobile()) {
            throw InvalidSmsMessageException::phoneNotMobile($phoneNumber->getValue());
        }

        if (trim($message) === '') {
            throw InvalidSmsMessageException::emptyMessage();
        }

        return new self($phoneNumber, $message);
    }

    public function withStatusCallback(): self
    {
        $clone = clone $this;
        $clone->statusCallbackEnabled = true;
        return $clone;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function hasStatusCallback(): bool
    {
        return $this->statusCallbackEnabled;
    }
}
