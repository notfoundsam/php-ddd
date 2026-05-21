<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use DateTimeImmutable;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserRepository\UserRepositoryInterface;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Domain\ValueObjects\EmailAddress;

class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, array{user: AuthenticatedUser, hash: string}> */
    private array $byEmail = [];

    /** @var array<string, AuthenticatedUser> */
    private array $byId = [];

    /** @var array<int|string, DateTimeImmutable> */
    public array $lastLoginUpdates = [];

    public function addUser(AuthenticatedUser $user, string $passwordHash): void
    {
        $this->byEmail[$user->getEmail()] = ['user' => $user, 'hash' => $passwordHash];
        $this->byId[$user->getId()] = $user;
    }

    public function findByEmail(EmailAddress $email): ?AuthenticatedUser
    {
        $row = $this->byEmail[(string)$email] ?? null;
        return $row === null ? null : $row['user'];
    }

    public function findById(string $id): ?AuthenticatedUser
    {
        return $this->byId[$id] ?? null;
    }

    public function getPasswordHashByEmail(EmailAddress $email): ?string
    {
        $row = $this->byEmail[(string)$email] ?? null;
        return $row === null ? null : $row['hash'];
    }

    public function updateLastLogin(string $id, DateTimeImmutable $at): void
    {
        $this->lastLoginUpdates[$id] = $at;
    }

    public static function makeUser(
        string $id,
        string $email,
        string $type = UserType::ADMIN,
        array $roles = []
    ): AuthenticatedUser {
        return new AuthenticatedUser($id, $email, $roles, $type);
    }
}
