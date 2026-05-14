<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Notification\Email;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Notification\Message\EmailSenderRole;
use SharedKernel\Infrastructure\Notification\Email\SenderRegistry;

class SenderRegistryTest extends TestCase
{
    public function testInfoRoleHasOwnAddressAndDisplayName(): void
    {
        $recipient = (new SenderRegistry('php-ddd.jp'))->getFromRecipient(EmailSenderRole::info());

        $this->assertSame('info@php-ddd.jp', $recipient->getEmail());
        $this->assertSame('php-ddd', $recipient->getDisplayName());
    }

    public function testMarketingRoleHasOwnAddressAndDisplayName(): void
    {
        $recipient = (new SenderRegistry('php-ddd.jp'))->getFromRecipient(EmailSenderRole::marketing());

        $this->assertSame('marketing@php-ddd.jp', $recipient->getEmail());
        $this->assertSame('Marketing', $recipient->getDisplayName());
    }

    public function testSystemRoleHasOwnAddressAndDisplayName(): void
    {
        $recipient = (new SenderRegistry('php-ddd.jp'))->getFromRecipient(EmailSenderRole::system());

        $this->assertSame('system@php-ddd.jp', $recipient->getEmail());
        $this->assertSame('System', $recipient->getDisplayName());
    }

    public function testDomainSubstitution(): void
    {
        $registry = new SenderRegistry('staging.php-ddd.jp');

        $this->assertSame(
            'system@staging.php-ddd.jp',
            $registry->getFromRecipient(EmailSenderRole::system())->getEmail()
        );
    }

    public function testConstructorRejectsEmptyDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SenderRegistry('');
    }

    public function testConstructorRejectsWhitespaceDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SenderRegistry('   ');
    }
}
