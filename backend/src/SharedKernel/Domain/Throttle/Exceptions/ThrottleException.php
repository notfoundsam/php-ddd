<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle\Exceptions;

use RuntimeException;

class ThrottleException extends RuntimeException
{
    private int $retryAfter;
    private string $identifier;

    public function __construct(int $retryAfter, string $identifier = '')
    {
        $this->retryAfter = $retryAfter;
        $this->identifier = $identifier;

        parent::__construct('Too many requests. Please try again later.');
    }

    public static function blockedIdentifier(int $retryAfter, string $identifier): self
    {
        return new self($retryAfter, $identifier);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
