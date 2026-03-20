<?php

declare(strict_types=1);

namespace SharedKernel\Domain;

final class Environment
{
    private string $value;
    private ?string $testEnvironmentId;

    private const PRODUCTION = 'production';
    private const STAGING = 'staging';
    private const TEST = 'test';
    private const DEVELOPMENT = 'development';
    private const LOCAL = 'local';

    public function __construct(string $environment, ?string $testEnvironmentId = null)
    {
        $this->value = $environment;
        $this->testEnvironmentId = $testEnvironmentId;
    }

    public function isProduction(): bool
    {
        return $this->value === self::PRODUCTION;
    }

    public function isStaging(): bool
    {
        return $this->value === self::STAGING;
    }

    public function isTest(): bool
    {
        return $this->value === self::TEST;
    }

    public function isDevelopment(): bool
    {
        return $this->value === self::DEVELOPMENT || $this->value === self::LOCAL;
    }

    public function isLocal(): bool
    {
        return $this->value === self::LOCAL;
    }

    /**
     * Returns true for cloud-hosted environments (production, staging, test)
     */
    public function isCloudLike(): bool
    {
        return $this->isProduction() || $this->isStaging() || $this->isTest();
    }

    /**
     * Returns true for staging and test environments
     */
    public function isStagingLike(): bool
    {
        return $this->isStaging() || $this->isTest();
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Returns the test environment ID if set (e.g., from TEST_ENV_ID environment variable)
     * Used for tagging emails and filtering in staging/test environments
     */
    public function getTestEnvironmentId(): ?string
    {
        return $this->testEnvironmentId;
    }

    /**
     * Returns true if a test environment ID is set and non-empty
     */
    public function hasTestEnvironmentId(): bool
    {
        return $this->testEnvironmentId !== null && $this->testEnvironmentId !== '';
    }
}
