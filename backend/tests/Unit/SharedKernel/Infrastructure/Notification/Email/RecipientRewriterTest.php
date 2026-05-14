<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Notification\Email;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use SharedKernel\Domain\ValueObjects\EmailRecipient;
use SharedKernel\Infrastructure\Notification\Email\RecipientRewriter;

class RecipientRewriterTest extends TestCase
{
    public function testRewriteSimpleAddress(): void
    {
        $rewriter = new RecipientRewriter();

        $result = $rewriter->rewrite(new EmailRecipient(new EmailAddress('user@example.com')));

        $this->assertSame('user--example.com@staging.php-ddd.jp', $result->getEmail());
    }

    public function testRewritePreservesPlusTag(): void
    {
        $rewriter = new RecipientRewriter();

        $result = $rewriter->rewrite(new EmailRecipient(new EmailAddress('user+marketing@example.com')));

        $this->assertSame('user+marketing--example.com@staging.php-ddd.jp', $result->getEmail());
    }

    public function testRewritePreservesDisplayName(): void
    {
        $rewriter = new RecipientRewriter();

        $result = $rewriter->rewrite(
            new EmailRecipient(new EmailAddress('user@example.com'), 'Jane Doe')
        );

        $this->assertSame('user--example.com@staging.php-ddd.jp', $result->getEmail());
        $this->assertSame('Jane Doe', $result->getDisplayName());
    }

    public function testRewriteIsIdempotentForAlreadyRewrittenAddress(): void
    {
        $rewriter = new RecipientRewriter();
        $already = new EmailRecipient(new EmailAddress('user--example.com@staging.php-ddd.jp'));

        $result = $rewriter->rewrite($already);

        $this->assertSame($already, $result);
    }

    public function testIsAlreadyRewrittenTrueForTargetDomain(): void
    {
        $rewriter = new RecipientRewriter();

        $this->assertTrue(
            $rewriter->isAlreadyRewritten(new EmailRecipient(new EmailAddress('whatever@staging.php-ddd.jp')))
        );
    }

    public function testIsAlreadyRewrittenFalseForOtherDomain(): void
    {
        $rewriter = new RecipientRewriter();

        $this->assertFalse(
            $rewriter->isAlreadyRewritten(new EmailRecipient(new EmailAddress('user@example.com')))
        );
    }
}
