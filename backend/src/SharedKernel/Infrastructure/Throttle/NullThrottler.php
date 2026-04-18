<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Domain\Throttle\ThrottleInterface;
use SharedKernel\Domain\Throttle\ThrottleResult;

final class NullThrottler implements ThrottleInterface
{
    public function attempt(string $identifier): ThrottleResult
    {
        return ThrottleResult::allowed(0);
    }

    public function clear(string $identifier): void
    {
    }
}
