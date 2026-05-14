<?php

declare(strict_types=1);

namespace SharedKernel\Domain\ValueObjects;

use InvalidArgumentException;

final class EmailRecipient
{
    private EmailAddress $address;
    private ?string $displayName;

    public function __construct(EmailAddress $address, ?string $displayName = null)
    {
        if ($displayName !== null) {
            $displayName = trim($displayName);
            if ($displayName === '') {
                $displayName = null;
            } elseif (strpbrk($displayName, "\r\n") !== false) {
                throw new InvalidArgumentException(
                    'EmailRecipient display name must not contain CR or LF characters'
                );
            }
        }

        $this->address = $address;
        $this->displayName = $displayName;
    }

    public static function fromString(string $email, ?string $displayName = null): self
    {
        return new self(new EmailAddress($email), $displayName);
    }

    public function getAddress(): EmailAddress
    {
        return $this->address;
    }

    public function getEmail(): string
    {
        return $this->address->getEmail();
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function hasDisplayName(): bool
    {
        return $this->displayName !== null;
    }

    public function withAddress(EmailAddress $address): self
    {
        return new self($address, $this->displayName);
    }

    public function equals(EmailRecipient $other): bool
    {
        return $this->address->equals($other->address)
            && $this->displayName === $other->displayName;
    }

    public function __toString(): string
    {
        if ($this->displayName !== null) {
            return $this->displayName . ' <' . $this->address->getEmail() . '>';
        }

        return $this->address->getEmail();
    }
}
