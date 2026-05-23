<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use Audience\Admin\Application\Command\Auth\LogInCommand as AdminLogInCommand;
use Audience\Partner\Application\Command\Auth\LogInCommand as PartnerLogInCommand;
use Audience\Site\Application\Command\Auth\LogInCommand as SiteLogInCommand;
use SharedKernel\Domain\Security\UserType;

final class ThrottleConfigDefaults
{
    /**
     * Strict login-attempt limit: 10 / 10 minutes for both anonymous (per-IP) and any
     * already-authenticated caller (per-user-id). Without the 'authenticated' branch a
     * stolen low-privilege session could hammer admin login at the per-user-type
     * default rate (admin = null = no throttle).
     *
     * Per ADR-014 §"Decorator chain for login commands" this delivers the OWASP-recommended
     * rate-limit on failed authentication with config only, no new code.
     */
    private const LOGIN_THROTTLE_WINDOW = [
        'warning_limit' => 7,
        'block_limit' => 10,
        'window' => 600,
        'penalties' => [600, 1800],
        'recovery_period' => 1800,
    ];

    private const LOGIN_THROTTLE = [
        'anonymous' => self::LOGIN_THROTTLE_WINDOW,
        'authenticated' => self::LOGIN_THROTTLE_WINDOW,
    ];

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
            'commands' => [
                AdminLogInCommand::class => self::LOGIN_THROTTLE,
                PartnerLogInCommand::class => self::LOGIN_THROTTLE,
                SiteLogInCommand::class => self::LOGIN_THROTTLE,
            ],
            'queries' => [],
            'excluded' => [],
        ];
    }
}
