<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Domain\Security\UserType;

final class ThrottleConfigDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        return [
            'defaults' => [
                UserType::ADMIN => null,
                UserType::PARTNER => [
                    'warning_limit' => 100,
                    'block_limit' => 200,
                    'window' => 60,
                    'penalties' => [300, 3600],
                    'recovery_period' => 3600,
                ],
                UserType::CUSTOMER => [
                    'warning_limit' => 30,
                    'block_limit' => 50,
                    'window' => 60,
                    'penalties' => [300, 3600, 10800],
                    'recovery_period' => 10800,
                ],
                // No anonymous default — broad anonymous throttling is handled at the HTTP layer.
                // Per-command anonymous overrides can still be added in the 'commands' section.
            ],
            'commands' => [],
            'queries' => [],
            'excluded' => [],
        ];
    }
}
