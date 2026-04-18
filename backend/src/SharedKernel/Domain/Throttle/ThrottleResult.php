<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle;

class ThrottleResult
{
    private bool $allowed;
    private bool $warningTriggered;
    private int $retryAfter;
    private int $currentAttempts;

    private function __construct(
        bool $allowed,
        bool $warningTriggered,
        int $retryAfter,
        int $currentAttempts
    ) {
        $this->allowed = $allowed;
        $this->warningTriggered = $warningTriggered;
        $this->retryAfter = $retryAfter;
        $this->currentAttempts = $currentAttempts;
    }

    public static function allowed(int $currentAttempts): self
    {
        return new self(true, false, 0, $currentAttempts);
    }

    public static function warning(int $currentAttempts): self
    {
        return new self(true, true, 0, $currentAttempts);
    }

    public static function blocked(int $retryAfter, int $currentAttempts = 0): self
    {
        return new self(false, false, $retryAfter, $currentAttempts);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isWarningTriggered(): bool
    {
        return $this->warningTriggered;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getCurrentAttempts(): int
    {
        return $this->currentAttempts;
    }
}
