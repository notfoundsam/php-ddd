<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle;

use SharedKernel\Domain\Throttle\Exceptions\ThrottleConfigException;

class ThrottleConfig
{
    private int $warningLimit;
    private int $blockLimit;
    private int $window;
    /** @var array<int> */
    private array $penalties;
    private int $recoveryPeriod;

    public function __construct(array $config)
    {
        $this->validateAndSetConfig($config);
    }

    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    private function validateAndSetConfig(array $config): void
    {
        if (!isset($config['warning_limit'], $config['block_limit'], $config['window'])) {
            throw ThrottleConfigException::missingRequiredKeys();
        }

        $this->warningLimit = $this->validatePositiveInteger($config['warning_limit'], 'warning_limit');
        $this->blockLimit = $this->validatePositiveInteger($config['block_limit'], 'block_limit');
        $this->window = $this->validatePositiveInteger($config['window'], 'window');

        if ($this->warningLimit >= $this->blockLimit) {
            throw ThrottleConfigException::warningLimitTooHigh();
        }

        $this->penalties = $config['penalties'] ?? [];
        $this->validatePenalties();

        $this->recoveryPeriod = $this->validatePositiveInteger(
            $config['recovery_period'] ?? $this->window,
            'recovery_period'
        );
    }

    private function validatePositiveInteger(int $value, string $key): int
    {
        if ($value <= 0) {
            throw ThrottleConfigException::invalidValue($key, 'must be a positive integer');
        }
        return $value;
    }

    private function validatePenalties(): void
    {
        foreach ($this->penalties as $index => $penalty) {
            if (!is_int($penalty) || $penalty <= 0) {
                throw ThrottleConfigException::invalidValue(
                    "penalties[$index]",
                    'must be a positive integer'
                );
            }
        }
    }

    public function getWarningLimit(): int
    {
        return $this->warningLimit;
    }

    public function getBlockLimit(): int
    {
        return $this->blockLimit;
    }

    public function getWindow(): int
    {
        return $this->window;
    }

    public function getRecoveryPeriod(): int
    {
        return $this->recoveryPeriod;
    }

    public function hasProgressivePenalties(): bool
    {
        return !empty($this->penalties);
    }

    public function getPenaltyDuration(int $violationNumber): int
    {
        if (empty($this->penalties)) {
            return $this->window;
        }

        $penaltyIndex = min($violationNumber - 1, count($this->penalties) - 1);
        return $this->penalties[$penaltyIndex];
    }
}
