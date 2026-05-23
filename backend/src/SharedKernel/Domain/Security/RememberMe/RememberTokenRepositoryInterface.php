<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\RememberMe;

use DateTimeImmutable;

interface RememberTokenRepositoryInterface
{
    public function insert(
        string $selector,
        string $audience,
        string $userId,
        string $validatorHash,
        DateTimeImmutable $expiresAt
    ): void;

    /**
     * @return array{
     *     selector: string,
     *     audience: string,
     *     user_id: string,
     *     validator_hash: string,
     *     expires_at: DateTimeImmutable
     * }|null
     */
    public function findBySelector(string $selector): ?array;

    public function rotate(string $selector, string $newValidatorHash, DateTimeImmutable $lastUsedAt): void;

    public function deleteBySelector(string $selector): void;

    public function deleteByUserAndAudience(string $userId, string $audience): void;

    /**
     * @return int number of deleted rows
     */
    public function purgeExpired(DateTimeImmutable $now): int;
}
