<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use DateTimeImmutable;
use SharedKernel\Domain\Security\RememberMe\RememberTokenRepositoryInterface;

final class InMemoryRememberTokenRepository implements RememberTokenRepositoryInterface
{
    /** @var array<string, array{audience: string, user_id: string, validator_hash: string, expires_at: DateTimeImmutable, last_used_at: ?DateTimeImmutable}> */
    public array $rows = [];

    public function insert(
        string $selector,
        string $audience,
        string $userId,
        string $validatorHash,
        DateTimeImmutable $expiresAt
    ): void {
        $this->rows[$selector] = [
            'audience' => $audience,
            'user_id' => $userId,
            'validator_hash' => $validatorHash,
            'expires_at' => $expiresAt,
            'last_used_at' => null,
        ];
    }

    public function findBySelector(string $selector): ?array
    {
        if (!isset($this->rows[$selector])) {
            return null;
        }
        $row = $this->rows[$selector];
        return [
            'selector' => $selector,
            'audience' => $row['audience'],
            'user_id' => $row['user_id'],
            'validator_hash' => $row['validator_hash'],
            'expires_at' => $row['expires_at'],
        ];
    }

    public function rotate(string $selector, string $newValidatorHash, DateTimeImmutable $lastUsedAt): void
    {
        if (!isset($this->rows[$selector])) {
            return;
        }
        $this->rows[$selector]['validator_hash'] = $newValidatorHash;
        $this->rows[$selector]['last_used_at'] = $lastUsedAt;
    }

    public function deleteBySelector(string $selector): void
    {
        unset($this->rows[$selector]);
    }

    public function deleteByUserAndAudience(string $userId, string $audience): void
    {
        foreach ($this->rows as $selector => $row) {
            if ($row['user_id'] !== $userId || $row['audience'] !== $audience) {
                continue;
            }
            unset($this->rows[$selector]);
        }
    }

    public function purgeExpired(DateTimeImmutable $now): int
    {
        $count = 0;
        foreach ($this->rows as $selector => $row) {
            if ($row['expires_at'] > $now) {
                continue;
            }
            unset($this->rows[$selector]);
            $count++;
        }
        return $count;
    }
}
