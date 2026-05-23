<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use DateTimeImmutable;
use Fuel\Core\DB;
use SharedKernel\Domain\Security\RememberMe\RememberTokenRepositoryInterface;

final class MysqlRememberTokenRepository implements RememberTokenRepositoryInterface
{
    private const TABLE = 'remember_tokens';

    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function insert(
        string $selector,
        string $audience,
        string $userId,
        string $validatorHash,
        DateTimeImmutable $expiresAt
    ): void {
        $now = (new DateTimeImmutable())->format(self::DATETIME_FORMAT);

        DB::insert(self::TABLE)->set([
            'selector' => $selector,
            'audience' => $audience,
            'user_id' => $userId,
            'validator_hash' => $validatorHash,
            'expires_at' => $expiresAt->format(self::DATETIME_FORMAT),
            'created_at' => $now,
            'last_used_at' => null,
        ])->execute();
    }

    public function findBySelector(string $selector): ?array
    {
        $row = DB::select('selector', 'audience', 'user_id', 'validator_hash', 'expires_at')
            ->from(self::TABLE)
            ->where('selector', $selector)
            ->execute()
            ->current();

        if ($row === null || $row === false || $row === []) {
            return null;
        }

        return [
            'selector' => (string)$row['selector'],
            'audience' => (string)$row['audience'],
            'user_id' => (string)$row['user_id'],
            'validator_hash' => (string)$row['validator_hash'],
            'expires_at' => new DateTimeImmutable((string)$row['expires_at']),
        ];
    }

    public function rotate(string $selector, string $newValidatorHash, DateTimeImmutable $lastUsedAt): void
    {
        DB::update(self::TABLE)
            ->set([
                'validator_hash' => $newValidatorHash,
                'last_used_at' => $lastUsedAt->format(self::DATETIME_FORMAT),
            ])
            ->where('selector', $selector)
            ->execute();
    }

    public function deleteBySelector(string $selector): void
    {
        DB::delete(self::TABLE)->where('selector', $selector)->execute();
    }

    public function deleteByUserAndAudience(string $userId, string $audience): void
    {
        DB::delete(self::TABLE)
            ->where('user_id', $userId)
            ->where('audience', $audience)
            ->execute();
    }

    public function purgeExpired(DateTimeImmutable $now): int
    {
        return (int)DB::delete(self::TABLE)
            ->where('expires_at', '<', $now->format(self::DATETIME_FORMAT))
            ->execute();
    }
}
