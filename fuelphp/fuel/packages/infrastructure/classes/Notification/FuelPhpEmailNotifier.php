<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use Email\Email;
use Email_Driver_Ses;
use Exception;
use Fuel\Core\Package;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Notification\Channel\EmailNotifierInterface;
use SharedKernel\Domain\Notification\Delivery\DeliveryReceipt;
use SharedKernel\Domain\Notification\Exception\NotificationDeliveryException;
use SharedKernel\Domain\Notification\Message\EmailMessage;
use SharedKernel\Domain\ValueObjects\EmailRecipient;
use SharedKernel\Infrastructure\Notification\Email\RecipientRewriter;
use SharedKernel\Infrastructure\Notification\Email\SenderRegistry;

/**
 * FuelPHP-backed email notifier.
 *
 * Delegates delivery to FuelPHP's Email\Email driver (selected by the
 * default_setup in config/email.php — typically 'ses' in cloud envs and
 * 'smtp' for Mailpit in dev). The FuelPHP driver handles MIME assembly,
 * attachments, and headers; this notifier maps EmailMessage -> driver calls
 * and applies env-specific behavior (recipient rewriting, configuration set).
 *
 * AWS SES configuration set names follow the convention:
 *   php-ddd-{env}-email-{alias}
 * (e.g. 'php-ddd-production-email-marketing'). Skipped in test env.
 */
final class FuelPhpEmailNotifier implements EmailNotifierInterface
{
    private const CONFIGURATION_SET_PROJECT = 'php-ddd';

    private SenderRegistry $senderRegistry;
    private RecipientRewriter $rewriter;
    private Environment $environment;

    public function __construct(
        SenderRegistry $senderRegistry,
        RecipientRewriter $rewriter,
        Environment $environment
    ) {
        $this->senderRegistry = $senderRegistry;
        $this->rewriter = $rewriter;
        $this->environment = $environment;
    }

    public function notify(EmailMessage $message): DeliveryReceipt
    {
        Package::load('email');

        try {
            $driver = Email::forge();

            $from = $this->senderRegistry->getFromRecipient($message->getSenderRole());
            $driver->from($from->getEmail(), $from->getDisplayName() ?? false);

            foreach ($message->getTo() as $recipient) {
                $resolved = $this->resolveRecipient($recipient);
                $driver->to($resolved->getEmail(), $resolved->getDisplayName() ?? false);
            }
            foreach ($message->getCc() as $recipient) {
                $resolved = $this->resolveRecipient($recipient);
                $driver->cc($resolved->getEmail(), $resolved->getDisplayName() ?? false);
            }
            foreach ($message->getBcc() as $recipient) {
                $resolved = $this->resolveRecipient($recipient);
                $driver->bcc($resolved->getEmail(), $resolved->getDisplayName() ?? false);
            }

            $driver->subject($message->getSubject());

            if ($message->getHtmlBody() !== null) {
                $driver->html_body($message->getHtmlBody());
            }
            if ($message->getTextBody() !== null) {
                $driver->alt_body($message->getTextBody());
            }

            foreach ($message->getAttachments() as $attachment) {
                $driver->string_attach(
                    $attachment['data'],
                    $attachment['filename'],
                    null,
                    false,
                    $attachment['mimeType']
                );
            }

            if ($message->hasOneClickUnsubscribe()) {
                $unsubscribeUrls = [];
                if ($message->getUnsubscribeHttpUrl() !== null) {
                    $unsubscribeUrls[] = '<' . $message->getUnsubscribeHttpUrl() . '>';
                }
                if ($message->getUnsubscribeMailtoUrl() !== null) {
                    $unsubscribeUrls[] = '<' . $message->getUnsubscribeMailtoUrl() . '>';
                }
                if ($unsubscribeUrls !== []) {
                    $driver->header('List-Unsubscribe', implode(', ', $unsubscribeUrls));
                    $driver->header('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            }

            $tags = $message->getTags();
            if ($tags !== []) {
                $driver->header('X-Tags', implode(', ', $tags));
            }

            $this->applyConfigurationSet($driver, $message);

            $success = $driver->send();
        } catch (Exception $e) {
            throw NotificationDeliveryException::providerError('FuelPHP/Email', $e->getMessage());
        }

        if (!$success) {
            throw NotificationDeliveryException::providerError(
                'FuelPHP/Email',
                $this->extractDriverError($driver) ?? 'driver returned false'
            );
        }

        return new DeliveryReceipt($this->extractMessageId($driver));
    }

    /**
     * @param object $driver
     */
    private function extractDriverError($driver): ?string
    {
        if (method_exists($driver, 'getLastErrorMessage')) {
            $message = $driver->getLastErrorMessage();
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }
        return null;
    }

    private function resolveRecipient(EmailRecipient $recipient): EmailRecipient
    {
        return $this->environment->isStaging() ? $this->rewriter->rewrite($recipient) : $recipient;
    }

    /**
     * @param object $driver
     */
    private function applyConfigurationSet($driver, EmailMessage $message): void
    {
        if (!$driver instanceof Email_Driver_Ses) {
            return;
        }

        $alias = $message->getConfigurationSet();
        if ($alias === null || $alias === '') {
            return;
        }

        if ($this->environment->isTest()) {
            return;
        }

        $name = sprintf(
            '%s-%s-email-%s',
            self::CONFIGURATION_SET_PROJECT,
            $this->environment->getValue(),
            $alias
        );

        $driver->set_configuration_set($name);
    }

    /**
     * @param object $driver
     */
    private function extractMessageId($driver): ?string
    {
        if (method_exists($driver, 'getMessageId')) {
            $id = $driver->getMessageId();
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }
        return null;
    }
}
