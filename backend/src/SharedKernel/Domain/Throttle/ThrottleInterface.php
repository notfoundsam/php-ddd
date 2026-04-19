<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle;

use SharedKernel\Domain\Throttle\Exceptions\ThrottleDriverException;

interface ThrottleInterface
{
    /**
     * Attempt to perform an action for the given identifier
     * @param string $identifier The unique identifier (e.g., 'ip:192.168.1.1' or 'user:12345')
     * @return ThrottleResult The result indicating if the action is allowed
     * @throws ThrottleDriverException
     */
    public function attempt(string $identifier): ThrottleResult;

    /**
     * Clear all throttling state for the given identifier (complete reset/unblock)
     * @param string $identifier The unique identifier to reset
     * @throws ThrottleDriverException
     */
    public function clear(string $identifier): void;
}
