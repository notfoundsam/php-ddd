<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use SharedKernel\Domain\Notification\Channel\SmsNotifierInterface;
use SharedKernel\Domain\Notification\Delivery\DeliveryReceipt;
use SharedKernel\Domain\Notification\Exception\NotificationDeliveryException;
use SharedKernel\Domain\Notification\Message\SmsMessage;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

final class TwilioSmsNotifier implements SmsNotifierInterface
{
    /** Twilio absolute hard limit on a single concatenated SMS body. */
    private const TWILIO_MAX_LENGTH = 1600;

    /**
     * Early-success states returned synchronously by Twilio's messages->create().
     * Terminal states (delivered/failed/undelivered) arrive later via webhook.
     */
    private const ACCEPTED_STATUSES = ['queued', 'accepted', 'sending'];

    private Client $client;
    private string $smsFrom;
    private ?string $callbackUrl;

    public function __construct(Client $client, string $smsFrom, ?string $callbackUrl = null)
    {
        $this->client = $client;
        $this->smsFrom = $smsFrom;
        $this->callbackUrl = $callbackUrl !== null && $callbackUrl !== '' ? $callbackUrl : null;
    }

    public function notify(SmsMessage $message): DeliveryReceipt
    {
        $body = $message->getMessage();

        if (mb_strlen($body, 'UTF-8') > self::TWILIO_MAX_LENGTH) {
            throw new NotificationDeliveryException(
                'SMS message exceeds Twilio limit of ' . self::TWILIO_MAX_LENGTH . ' characters'
            );
        }

        $options = [
            'from' => $this->smsFrom,
            'body' => $body,
        ];

        if ($message->hasStatusCallback() && $this->callbackUrl !== null) {
            $options['statusCallback'] = $this->callbackUrl . '/sms-main';
        }

        try {
            $response = $this->client->messages->create(
                $message->getPhoneNumber()->toInternationalFormat(),
                $options
            );
        } catch (TwilioException $e) {
            throw NotificationDeliveryException::providerError('Twilio', $e->getMessage());
        }

        if (!in_array($response->status, self::ACCEPTED_STATUSES, true) || empty($response->sid)) {
            throw new NotificationDeliveryException(sprintf(
                'SMS delivery failed: unexpected status "%s" or empty SID',
                (string) $response->status
            ));
        }

        return new DeliveryReceipt($response->sid);
    }
}
