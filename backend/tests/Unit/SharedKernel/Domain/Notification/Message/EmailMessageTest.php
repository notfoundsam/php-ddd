<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Notification\Message;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Notification\Exception\InvalidEmailMessageException;
use SharedKernel\Domain\Notification\Message\EmailMessage;
use SharedKernel\Domain\Notification\Message\EmailSenderRole;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use SharedKernel\Domain\ValueObjects\EmailRecipient;

class EmailMessageTest extends TestCase
{
    public function testCreateWithMinimalFields(): void
    {
        $message = EmailMessage::create(
            EmailSenderRole::system(),
            ['user@example.com'],
            'Hello',
            '<p>Hi</p>'
        );

        $this->assertSame('Hello', $message->getSubject());
        $this->assertSame('<p>Hi</p>', $message->getHtmlBody());
        $this->assertNull($message->getTextBody());
        $this->assertSame('system', $message->getSenderRole()->getValue());
        $this->assertCount(1, $message->getTo());
        $this->assertSame('user@example.com', $message->getTo()[0]->getEmail());
        $this->assertNull($message->getTo()[0]->getDisplayName());
    }

    public function testCreateNormalizesStringRecipientsToEmailRecipient(): void
    {
        $message = EmailMessage::create(
            EmailSenderRole::system(),
            ['jane@example.com', 'plain@example.com'],
            'Hi',
            null,
            'text body'
        );

        $this->assertCount(2, $message->getTo());
        $this->assertSame('jane@example.com', $message->getTo()[0]->getEmail());
        $this->assertNull($message->getTo()[0]->getDisplayName());
        $this->assertSame('plain@example.com', $message->getTo()[1]->getEmail());
    }

    public function testCreateAcceptsEmailRecipientInstancesWithDisplayName(): void
    {
        $recipient = new EmailRecipient(new EmailAddress('user@example.com'), 'User Name');

        $message = EmailMessage::create(
            EmailSenderRole::system(),
            [$recipient],
            'Hi',
            null,
            'text'
        );

        $this->assertSame($recipient, $message->getTo()[0]);
        $this->assertSame('User Name', $message->getTo()[0]->getDisplayName());
    }

    public function testCreateWithCcAndBcc(): void
    {
        $message = EmailMessage::create(
            EmailSenderRole::system(),
            ['to@example.com'],
            'Hi',
            '<p>x</p>',
            null,
            ['cc@example.com'],
            ['bcc@example.com']
        );

        $this->assertSame('cc@example.com', $message->getCc()[0]->getEmail());
        $this->assertSame('bcc@example.com', $message->getBcc()[0]->getEmail());
    }

    public function testCreateRejectsEmptyToList(): void
    {
        $this->expectException(InvalidEmailMessageException::class);

        EmailMessage::create(EmailSenderRole::system(), [], 'Hi', '<p>x</p>');
    }

    public function testCreateRejectsEmptySubject(): void
    {
        $this->expectException(InvalidEmailMessageException::class);

        EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], '   ', '<p>x</p>');
    }

    public function testCreateRejectsBothBodiesNull(): void
    {
        $this->expectException(InvalidEmailMessageException::class);

        EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi');
    }

    public function testWithTagIsImmutable(): void
    {
        $original = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>');
        $tagged = $original->withTag('campaign-spring');

        $this->assertSame([], $original->getTags());
        $this->assertSame(['campaign-spring'], $tagged->getTags());
    }

    public function testWithTagAppendsAndDeduplicates(): void
    {
        $message = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>')
            ->withTag('transactional')
            ->withTag('order-confirmation')
            ->withTag('transactional');

        $this->assertSame(['transactional', 'order-confirmation'], $message->getTags());
    }

    public function testWithTagIgnoresEmptyTag(): void
    {
        $message = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>')
            ->withTag('   ');

        $this->assertSame([], $message->getTags());
    }

    public function testWithTagsAppliesAll(): void
    {
        $message = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>')
            ->withTags(['marketing', 'spring-sale', 'marketing']);

        $this->assertSame(['marketing', 'spring-sale'], $message->getTags());
    }

    public function testWithConfigurationSet(): void
    {
        $message = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>')
            ->withConfigurationSet('marketing');

        $this->assertSame('marketing', $message->getConfigurationSet());
    }

    public function testWithStringAttachment(): void
    {
        $message = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>')
            ->withStringAttachment('binary-data', 'invoice.pdf', 'application/pdf');

        $this->assertCount(1, $message->getAttachments());
        $this->assertSame('binary-data', $message->getAttachments()[0]['data']);
        $this->assertSame('invoice.pdf', $message->getAttachments()[0]['filename']);
        $this->assertSame('application/pdf', $message->getAttachments()[0]['mimeType']);
    }

    public function testWithOneClickUnsubscribe(): void
    {
        $message = EmailMessage::create(EmailSenderRole::marketing(), ['u@example.com'], 'Hi', '<p>x</p>')
            ->withOneClickUnsubscribe('https://example.com/u/abc', 'mailto:unsub@example.com');

        $this->assertTrue($message->hasOneClickUnsubscribe());
        $this->assertSame('https://example.com/u/abc', $message->getUnsubscribeHttpUrl());
        $this->assertSame('mailto:unsub@example.com', $message->getUnsubscribeMailtoUrl());
    }

    /**
     * @dataProvider crlfStringsProvider
     */
    public function testCreateRejectsCrlfInSubject(string $subject): void
    {
        $this->expectException(InvalidEmailMessageException::class);

        EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], $subject, '<p>x</p>');
    }

    /**
     * @dataProvider crlfStringsProvider
     */
    public function testWithTagRejectsCrlf(string $tag): void
    {
        $message = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>');

        $this->expectException(InvalidEmailMessageException::class);

        $message->withTag($tag);
    }

    /**
     * @dataProvider crlfStringsProvider
     */
    public function testWithOneClickUnsubscribeRejectsCrlfInHttpUrl(string $url): void
    {
        $message = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>');

        $this->expectException(InvalidEmailMessageException::class);

        $message->withOneClickUnsubscribe($url);
    }

    /**
     * @dataProvider crlfStringsProvider
     */
    public function testWithOneClickUnsubscribeRejectsCrlfInMailtoUrl(string $url): void
    {
        $message = EmailMessage::create(EmailSenderRole::system(), ['u@example.com'], 'Hi', '<p>x</p>');

        $this->expectException(InvalidEmailMessageException::class);

        $message->withOneClickUnsubscribe('https://example.com/u/abc', $url);
    }

    /**
     * @return array<string, array{string}>
     */
    public function crlfStringsProvider(): array
    {
        return [
            'CR in middle' => ["foo\rbar"],
            'LF in middle' => ["foo\nbar"],
            'CRLF in middle' => ["foo\r\nbar"],
        ];
    }
}
