<?php

declare(strict_types=1);

namespace SharedKernel\Domain\ValueObjects;

use SharedKernel\Domain\ValueObjects\Exception\InvalidEmailAddressException;

final class EmailAddress
{
    private string $email;

    public function __construct(string $email)
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            throw InvalidEmailAddressException::empty();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailAddressException::invalidFormat($email);
        }

        $this->email = $email;
    }

    public static function fromString(string $email): self
    {
        return new self($email);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function equals(EmailAddress $other): bool
    {
        return $this->email === $other->email;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
