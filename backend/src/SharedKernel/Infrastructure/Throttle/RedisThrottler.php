<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Throttle;

use SharedKernel\Domain\Redis\Exceptions\RedisConnectionException;
use SharedKernel\Domain\Redis\RedisMasterClientInterface;
use SharedKernel\Domain\Throttle\Exceptions\ThrottleDriverException;
use SharedKernel\Domain\Throttle\ThrottleConfig;
use SharedKernel\Domain\Throttle\ThrottleInterface;
use SharedKernel\Domain\Throttle\ThrottleResult;

class RedisThrottler implements ThrottleInterface
{
    private const REDIS_KEY_PREFIX = 'throttle';

    protected RedisMasterClientInterface $redis;
    protected ThrottleConfig $config;
    protected string $type;

    public function __construct(RedisMasterClientInterface $redis, ThrottleConfig $config, string $type)
    {
        $this->redis = $redis;
        $this->config = $config;
        $this->type = $type;
    }

    public function attempt(string $identifier): ThrottleResult
    {
        try {
            if ($this->isBlocked($identifier)) {
                $retryAfter = $this->getBlockedUntil($identifier) - time();
                return ThrottleResult::blocked(max(0, $retryAfter));
            }

            // Generate time window key
            $windowId = (int) floor(time() / $this->config->getWindow());
            $countKey = self::REDIS_KEY_PREFIX . ":$this->type:$identifier:window:$windowId";

            // Increment counter atomically — incr creates the key if missing
            $count = $this->redis->incr($countKey);
            if ($count === 1) {
                // First request in this window — set TTL
                $this->redis->expire($countKey, $this->config->getWindow());
            }

            if ($count >= $this->config->getBlockLimit()) {
                $retryAfter = $this->blockIdentifier($identifier);
                return ThrottleResult::blocked($retryAfter, $count);
            }

            if ($count >= $this->config->getWarningLimit()) {
                return ThrottleResult::warning($count);
            }

            return ThrottleResult::allowed($count);
        } catch (RedisConnectionException $e) {
            throw new ThrottleDriverException($e->getMessage(), $e);
        }
    }

    public function clear(string $identifier): void
    {
        try {
            // Clear all throttle-related keys for the identifier
            $baseKey = self::REDIS_KEY_PREFIX . ":$this->type:$identifier";

            // Get all possible keys to clear
            $keysToDelete = [];

            // Current and recent window keys (clear last few windows to be safe)
            $currentWindowId = (int) floor(time() / $this->config->getWindow());
            for ($i = 0; $i <= 2; $i++) {
                $windowKey = "$baseKey:window:" . ($currentWindowId - $i);
                $keysToDelete[] = $windowKey;
            }

            // Violation and blocked keys
            $keysToDelete[] = "$baseKey:violations";
            $keysToDelete[] = "$baseKey:blocked";

            // Delete all keys
            foreach ($keysToDelete as $key) {
                $this->redis->del($key);
            }
        } catch (RedisConnectionException $e) {
            throw new ThrottleDriverException($e->getMessage(), $e);
        }
    }

    private function isBlocked(string $identifier): bool
    {
        $blockedKey = self::REDIS_KEY_PREFIX . ":$this->type:$identifier:blocked";
        $blockedUntil = $this->redis->get($blockedKey);

        return $blockedUntil !== null && (int) $blockedUntil > time();
    }

    private function getBlockedUntil(string $identifier): int
    {
        $blockedKey = self::REDIS_KEY_PREFIX . ":$this->type:$identifier:blocked";
        $blockedUntil = $this->redis->get($blockedKey);

        return $blockedUntil !== null ? (int) $blockedUntil : 0;
    }

    private function blockIdentifier(string $identifier): int
    {
        $blockDuration = $this->getBlockDuration($identifier);
        $blockedUntil = time() + $blockDuration;

        $blockedKey = self::REDIS_KEY_PREFIX . ":$this->type:$identifier:blocked";
        $this->redis->set($blockedKey, (string) $blockedUntil, $blockDuration);

        return $blockDuration;
    }

    private function getBlockDuration(string $identifier): int
    {
        if (!$this->config->hasProgressivePenalties()) {
            return $this->config->getWindow();
        }

        $violations = $this->incrementViolations($identifier);
        return $this->config->getPenaltyDuration($violations);
    }

    private function incrementViolations(string $identifier): int
    {
        $violationKey = self::REDIS_KEY_PREFIX . ":$this->type:$identifier:violations";

        $violations = $this->redis->incr($violationKey);
        if ($violations === 1) {
            $this->redis->expire($violationKey, $this->config->getRecoveryPeriod());
        }

        return $violations;
    }
}
