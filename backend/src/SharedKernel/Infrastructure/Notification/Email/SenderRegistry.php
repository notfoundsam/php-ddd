<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Notification\Email;

use InvalidArgumentException;
use SharedKernel\Domain\Notification\Message\EmailSenderRole;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use SharedKernel\Domain\ValueObjects\EmailRecipient;

/**
 * Maps sender roles (info/marketing/system) to From recipients.
 *
 * Email domain is provided at construction time (typically from an env var).
 * Local part and display name per role are fixed in this class.
 */
final class SenderRegistry
{
    private string $domain;

    public function __construct(string $domain)
    {
        if (trim($domain) === '') {
            throw new InvalidArgumentException('SenderRegistry domain cannot be empty');
        }

        $this->domain = $domain;
    }

    public function getFromRecipient(EmailSenderRole $role): EmailRecipient
    {
        switch ($role->getValue()) {
            case EmailSenderRole::INFO:
                return new EmailRecipient(new EmailAddress("info@$this->domain"), 'php-ddd');
            case EmailSenderRole::MARKETING:
                return new EmailRecipient(new EmailAddress("marketing@$this->domain"), 'Marketing');
            case EmailSenderRole::SYSTEM:
                return new EmailRecipient(new EmailAddress("system@$this->domain"), 'System');
        }

        // EmailSenderRole VO guarantees one of the constants above; this is unreachable.
        throw new InvalidArgumentException("Unhandled sender role: '{$role->getValue()}'");
    }
}
