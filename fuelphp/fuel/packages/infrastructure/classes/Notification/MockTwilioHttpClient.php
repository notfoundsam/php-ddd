<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use Twilio\AuthStrategy\AuthStrategy;
use Twilio\Http\CurlClient;
use Twilio\Http\Response;

/**
 * Twilio HTTP client that redirects API calls to a mock server in non-production environments,
 * keeping the path/query so the mock can mimic Twilio's API surface.
 */
final class MockTwilioHttpClient extends CurlClient
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        parent::__construct();
        $this->baseUrl = $baseUrl;
    }

    public function request(
        string $method,
        string $url,
        array $params = [],
        array $data = [],
        array $headers = [],
        string $user = null,
        string $password = null,
        int $timeout = null,
        ?AuthStrategy $authStrategy = null
    ): Response {
        $url = preg_replace('#^https?://[^/]+#', $this->baseUrl, $url);

        return parent::request(
            $method,
            $url,
            $params,
            $data,
            $headers,
            $user,
            $password,
            $timeout,
            $authStrategy
        );
    }
}
