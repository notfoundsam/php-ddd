<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use SharedKernel\Domain\Notification\Channel\SmsNotifierInterface;
use Twilio\Rest\Client;

/**
 * Builds the SMS notifier. When TWILIO_API_BASE_URL is set, the Twilio Client
 * is wired to a mock HTTP server (e.g. sms-mock-server in docker) — used in
 * development and test. Otherwise it talks to the real Twilio API.
 *
 * Twilio credentials and from-number come from env vars; this factory is the
 * one place that reads them.
 */
final class SmsNotifierFactory
{
    public function __invoke(): SmsNotifierInterface
    {
        $mockBase = getenv('TWILIO_API_BASE_URL');
        $httpClient = is_string($mockBase) && $mockBase !== ''
            ? new MockTwilioHttpClient($mockBase)
            : null;

        $client = new Client(
            (string) getenv('TWILIO_ACCOUNT_SID'),
            (string) getenv('TWILIO_AUTH_TOKEN'),
            null,
            null,
            $httpClient
        );

        $callbackUrl = getenv('TWILIO_CALLBACK_URL');

        return new TwilioSmsNotifier(
            $client,
            (string) getenv('TWILIO_SMS_FROM'),
            is_string($callbackUrl) && $callbackUrl !== '' ? $callbackUrl : null
        );
    }
}
