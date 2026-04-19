<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle;

interface ThrottleFactoryInterface
{
    public function create(ThrottleConfig $config, string $name): ThrottleInterface;
}
