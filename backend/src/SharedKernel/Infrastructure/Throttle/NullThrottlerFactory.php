<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Domain\Throttle\ThrottleConfig;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;
use SharedKernel\Domain\Throttle\ThrottleInterface;

final class NullThrottlerFactory implements ThrottleFactoryInterface
{
    public function create(ThrottleConfig $config, string $name): ThrottleInterface
    {
        return new NullThrottler();
    }
}
