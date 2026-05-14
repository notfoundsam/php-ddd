<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Notification\Email;

use SharedKernel\Domain\ValueObjects\EmailAddress;
use SharedKernel\Domain\ValueObjects\EmailRecipient;

/**
 * Rewrites recipient addresses for non-production environments to route through
 * an internally-hosted mail catcher (Mailpit) while still using the real SES
 * pipeline.
 *
 * Example: user+tag@example.com -> user+tag--example.com@staging.php-ddd.jp
 *
 * Display name is preserved on the rewritten recipient.
 */
final class RecipientRewriter
{
    private const STAGING_DOMAIN = 'staging.php-ddd.jp';
    private const DELIMITER = '--';

    public function rewrite(EmailRecipient $original): EmailRecipient
    {
        if ($this->isAlreadyRewritten($original)) {
            return $original;
        }

        [$localPart, $domain] = explode('@', $original->getEmail(), 2);

        $rewrittenEmail = $localPart . self::DELIMITER . $domain . '@' . self::STAGING_DOMAIN;

        return $original->withAddress(new EmailAddress($rewrittenEmail));
    }

    public function isAlreadyRewritten(EmailRecipient $recipient): bool
    {
        $needle = '@' . self::STAGING_DOMAIN;
        $haystack = $recipient->getEmail();
        $needleLength = strlen($needle);

        return $needleLength <= strlen($haystack)
            && substr($haystack, -$needleLength) === $needle;
    }
}
