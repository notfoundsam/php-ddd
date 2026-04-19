<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle;

final class ThrottleResolveResult
{
    private const DEFAULT_SCOPE = '__default__';

    private ThrottleConfig $config;
    private string $scope;

    private function __construct(ThrottleConfig $config, string $scope)
    {
        $this->config = $config;
        $this->scope = $scope;
    }

    public static function forCommand(ThrottleConfig $config, string $commandName): self
    {
        return new self($config, $commandName);
    }

    public static function forDefault(ThrottleConfig $config): self
    {
        return new self($config, self::DEFAULT_SCOPE);
    }

    public function getConfig(): ThrottleConfig
    {
        return $this->config;
    }

    public function getScope(): string
    {
        return $this->scope;
    }
}
