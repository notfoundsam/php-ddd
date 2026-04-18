<?php

declare(strict_types=1);

namespace SharedKernel\Application\Throttle;

use SharedKernel\Domain\Throttle\ThrottleResolveResult;

interface ThrottleConfigResolverInterface
{
    /**
     * Resolve the throttle configuration for a given CQRS message and user type.
     *
     * Returns null if throttle should be skipped (excluded message, exempt user type, or no config).
     * Returns ThrottleResolveResult with scope indicating per-command or default resolution.
     *
     * @param string $messageClass FQCN of the command or query
     * @param string $messageType 'command' or 'query'
     * @param string|null $userType UserType constant, or null for anonymous users
     * @return ThrottleResolveResult|null
     */
    public function resolve(string $messageClass, string $messageType, ?string $userType): ?ThrottleResolveResult;
}
