<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use DateTimeImmutable;
use Fuel\Core\DB;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserRepository\UserRepositoryInterface;
use SharedKernel\Domain\ValueObjects\EmailAddress;

abstract class FuelPhpUserRepository implements UserRepositoryInterface
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    private string $usersTable;

    private string $rolesTable;

    private string $rolesUserIdColumn;

    private string $userType;

    public function __construct(
        string $usersTable,
        string $rolesTable,
        string $rolesUserIdColumn,
        string $userType
    ) {
        $this->usersTable = $usersTable;
        $this->rolesTable = $rolesTable;
        $this->rolesUserIdColumn = $rolesUserIdColumn;
        $this->userType = $userType;
    }

    final public function findByEmail(EmailAddress $email): ?AuthenticatedUser
    {
        $row = DB::select('id', 'email')
            ->from($this->usersTable)
            ->where('email', (string)$email)
            ->execute()
            ->current();

        if ($row === null || $row === false || $row === []) {
            return null;
        }

        $id = (string)$row['id'];

        return new AuthenticatedUser($id, (string)$row['email'], $this->loadRoles($id), $this->userType);
    }

    final public function findById(string $id): ?AuthenticatedUser
    {
        $row = DB::select('id', 'email')
            ->from($this->usersTable)
            ->where('id', $id)
            ->execute()
            ->current();

        if ($row === null || $row === false || $row === []) {
            return null;
        }

        $userId = (string)$row['id'];

        return new AuthenticatedUser($userId, (string)$row['email'], $this->loadRoles($userId), $this->userType);
    }

    final public function getPasswordHashByEmail(EmailAddress $email): ?string
    {
        $row = DB::select('password')
            ->from($this->usersTable)
            ->where('email', (string)$email)
            ->execute()
            ->current();

        if ($row === null || $row === false || $row === []) {
            return null;
        }

        return (string)$row['password'];
    }

    final public function updateLastLogin(string $id, DateTimeImmutable $at): void
    {
        DB::update($this->usersTable)
            ->set([
                'last_login_at' => $at->format(self::DATETIME_FORMAT),
                'updated_at' => $at->format(self::DATETIME_FORMAT),
            ])
            ->where('id', $id)
            ->execute();
    }

    /**
     * @return array<string>
     */
    private function loadRoles(string $userId): array
    {
        $rows = DB::select('role')
            ->from($this->rolesTable)
            ->where($this->rolesUserIdColumn, $userId)
            ->execute()
            ->as_array();

        $roles = [];
        foreach ($rows as $row) {
            $roles[] = (string)$row['role'];
        }
        return $roles;
    }
}
