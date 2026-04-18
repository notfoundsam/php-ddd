<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Application\Throttle\ThrottleConfigResolverInterface;
use SharedKernel\Domain\Throttle\ThrottleConfig;
use SharedKernel\Domain\Throttle\ThrottleResolveResult;

final class ThrottleConfigResolver implements ThrottleConfigResolverInterface
{
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function resolve(string $messageClass, string $messageType, ?string $userType): ?ThrottleResolveResult
    {
        // 1. Excluded?
        if ($this->isExcluded($messageClass)) {
            return null;
        }

        $key = $userType ?? 'anonymous';
        $section = $messageType === 'command' ? 'commands' : 'queries';
        $messageConfig = $this->config[$section][$messageClass] ?? null;
        $shortName = substr(strrchr($messageClass, '\\'), 1) ?: $messageClass;

        if ($messageConfig !== null) {
            // 2. Exact user type match in per-message config
            if (isset($messageConfig[$key]) && is_array($messageConfig[$key])) {
                return ThrottleResolveResult::forCommand(ThrottleConfig::fromArray($messageConfig[$key]), $shortName);
            }

            // 3. Catch-all 'authenticated' key for any authenticated user
            if (
                $key !== 'anonymous'
                && isset($messageConfig['authenticated'])
                && is_array($messageConfig['authenticated'])
            ) {
                return ThrottleResolveResult::forCommand(
                    ThrottleConfig::fromArray($messageConfig['authenticated']),
                    $shortName
                );
            }

            // 4. Flat config (has 'warning_limit' at top level)
            if (isset($messageConfig['warning_limit'])) {
                return ThrottleResolveResult::forCommand(ThrottleConfig::fromArray($messageConfig), $shortName);
            }
        }

        // 5. Default for this user type
        if (array_key_exists($key, $this->config['defaults'] ?? [])) {
            $default = $this->config['defaults'][$key];

            if ($default === null) {
                return null;
            }

            return ThrottleResolveResult::forDefault(ThrottleConfig::fromArray($default));
        }

        // 6. No match
        return null;
    }

    private function isExcluded(string $messageClass): bool
    {
        return in_array($messageClass, $this->config['excluded'] ?? [], true);
    }
}
