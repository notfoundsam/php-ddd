<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Notification\Message;

use SharedKernel\Domain\Notification\Exception\InvalidEmailMessageException;
use SharedKernel\Domain\ValueObjects\EmailRecipient;

final class EmailMessage
{
    private EmailSenderRole $senderRole;
    /** @var EmailRecipient[] */
    private array $to;
    /** @var EmailRecipient[] */
    private array $cc;
    /** @var EmailRecipient[] */
    private array $bcc;
    private string $subject;
    private ?string $htmlBody;
    private ?string $textBody;
    /** @var array<int, array{data: string, filename: string, mimeType: string}> */
    private array $attachments;
    /** @var string[] */
    private array $tags;
    private ?string $configurationSet;
    private bool $oneClickUnsubscribe;
    private ?string $unsubscribeHttpUrl;
    private ?string $unsubscribeMailtoUrl;

    /**
     * @param EmailRecipient[] $to
     * @param EmailRecipient[] $cc
     * @param EmailRecipient[] $bcc
     */
    private function __construct(
        EmailSenderRole $senderRole,
        array $to,
        string $subject,
        ?string $htmlBody,
        ?string $textBody,
        array $cc,
        array $bcc
    ) {
        if ($to === []) {
            throw InvalidEmailMessageException::noRecipients();
        }

        if (trim($subject) === '') {
            throw InvalidEmailMessageException::emptySubject();
        }

        if (self::containsCrlf($subject)) {
            throw InvalidEmailMessageException::headerInjection('subject');
        }

        if ($htmlBody === null && $textBody === null) {
            throw InvalidEmailMessageException::noBody();
        }

        $this->senderRole = $senderRole;
        $this->to = $to;
        $this->cc = $cc;
        $this->bcc = $bcc;
        $this->subject = $subject;
        $this->htmlBody = $htmlBody;
        $this->textBody = $textBody;
        $this->attachments = [];
        $this->tags = [];
        $this->configurationSet = null;
        $this->oneClickUnsubscribe = false;
        $this->unsubscribeHttpUrl = null;
        $this->unsubscribeMailtoUrl = null;
    }

    /**
     * @param array<int, EmailRecipient|string> $to
     * @param array<int, EmailRecipient|string> $cc
     * @param array<int, EmailRecipient|string> $bcc
     */
    public static function create(
        EmailSenderRole $senderRole,
        array $to,
        string $subject,
        ?string $htmlBody = null,
        ?string $textBody = null,
        array $cc = [],
        array $bcc = []
    ): self {
        return new self(
            $senderRole,
            self::normalizeRecipients($to),
            $subject,
            $htmlBody,
            $textBody,
            self::normalizeRecipients($cc),
            self::normalizeRecipients($bcc)
        );
    }

    /**
     * @param array<int, EmailRecipient|string> $recipients
     * @return EmailRecipient[]
     */
    private static function normalizeRecipients(array $recipients): array
    {
        $normalized = [];

        foreach ($recipients as $value) {
            if ($value instanceof EmailRecipient) {
                $normalized[] = $value;
            } elseif (is_string($value)) {
                $normalized[] = EmailRecipient::fromString($value);
            }
        }

        return $normalized;
    }

    public function withTag(string $tag): self
    {
        $tag = trim($tag);
        if ($tag === '' || in_array($tag, $this->tags, true)) {
            return $this;
        }

        if (self::containsCrlf($tag)) {
            throw InvalidEmailMessageException::headerInjection('tag');
        }

        $clone = clone $this;
        $clone->tags[] = $tag;
        return $clone;
    }

    /**
     * @param string[] $tags
     */
    public function withTags(array $tags): self
    {
        $clone = $this;
        foreach ($tags as $tag) {
            $clone = $clone->withTag($tag);
        }
        return $clone;
    }

    public function withConfigurationSet(string $configurationSet): self
    {
        $clone = clone $this;
        $clone->configurationSet = $configurationSet;
        return $clone;
    }

    public function withStringAttachment(
        string $data,
        string $filename,
        string $mimeType = 'application/octet-stream'
    ): self {
        $clone = clone $this;
        $clone->attachments[] = ['data' => $data, 'filename' => $filename, 'mimeType' => $mimeType];
        return $clone;
    }

    public function withOneClickUnsubscribe(string $httpUrl, ?string $mailtoUrl = null): self
    {
        if (self::containsCrlf($httpUrl)) {
            throw InvalidEmailMessageException::headerInjection('unsubscribe HTTP URL');
        }

        if ($mailtoUrl !== null && self::containsCrlf($mailtoUrl)) {
            throw InvalidEmailMessageException::headerInjection('unsubscribe mailto URL');
        }

        $clone = clone $this;
        $clone->oneClickUnsubscribe = true;
        $clone->unsubscribeHttpUrl = $httpUrl;
        $clone->unsubscribeMailtoUrl = $mailtoUrl;
        return $clone;
    }

    private static function containsCrlf(string $value): bool
    {
        return strpbrk($value, "\r\n") !== false;
    }

    public function getSenderRole(): EmailSenderRole
    {
        return $this->senderRole;
    }

    /** @return EmailRecipient[] */
    public function getTo(): array
    {
        return $this->to;
    }

    /** @return EmailRecipient[] */
    public function getCc(): array
    {
        return $this->cc;
    }

    /** @return EmailRecipient[] */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtmlBody(): ?string
    {
        return $this->htmlBody;
    }

    public function getTextBody(): ?string
    {
        return $this->textBody;
    }

    /** @return array<int, array{data: string, filename: string, mimeType: string}> */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /** @return string[] */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getConfigurationSet(): ?string
    {
        return $this->configurationSet;
    }

    public function hasOneClickUnsubscribe(): bool
    {
        return $this->oneClickUnsubscribe;
    }

    public function getUnsubscribeHttpUrl(): ?string
    {
        return $this->unsubscribeHttpUrl;
    }

    public function getUnsubscribeMailtoUrl(): ?string
    {
        return $this->unsubscribeMailtoUrl;
    }
}
