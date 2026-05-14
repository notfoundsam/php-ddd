<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Exception;

use InvalidArgumentException;

class InvalidEmailMessageException extends InvalidArgumentException
{
    public static function noRecipients(): self
    {
        return new self('Email message must have at least one recipient');
    }

    public static function emptySubject(): self
    {
        return new self('Email message subject cannot be empty');
    }

    public static function noBody(): self
    {
        return new self('Email message must have at least one body (html or text)');
    }

    public static function headerInjection(string $field): self
    {
        return new self(sprintf(
            'Email message %s must not contain CR or LF characters',
            $field
        ));
    }
}
