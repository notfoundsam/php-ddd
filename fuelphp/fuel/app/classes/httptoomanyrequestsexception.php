<?php

use Fuel\Core\HttpException;
use Fuel\Core\Response;

class HttpTooManyRequestsException extends HttpException
{
    private int $retryAfter;

    public function __construct(int $retryAfter = 0, string $message = 'Too many requests. Please try again later.')
    {
        $this->retryAfter = $retryAfter;
        parent::__construct($message);
    }

    public function response(): Response
    {
        $response = new Response('Too many requests. Please try again later.', 429);

        if ($this->retryAfter > 0) {
            $response->set_header('Retry-After', (string) $this->retryAfter);
        }

        return $response;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
