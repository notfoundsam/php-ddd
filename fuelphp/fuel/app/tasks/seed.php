<?php

namespace Fuel\Tasks;

use Container;
use DateTimeImmutable;
use Fuel\Core\Cli;
use Fuel\Core\DB;
use SharedKernel\Domain\Security\PasswordHasher\PasswordHasherInterface;

class Seed
{
    public static function admin($email = null, $password = null, $role = 'admin')
    {
        if (!$email || !$password) {
            return Cli::color('Usage: oil refine seed:admin <email> <password> [role]', 'red');
        }

        $hasher = Container::resolve(PasswordHasherInterface::class);
        $hash = $hasher->hash((string)$password);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        DB::start_transaction();
        try {
            $existing = DB::select('id')->from('admin_users')->where('email', $email)->execute()->current();
            if ($existing !== null && $existing !== false && $existing !== []) {
                $id = (string)$existing['id'];
                DB::update('admin_users')
                    ->set(['password' => $hash, 'updated_at' => $now])
                    ->where('id', $id)
                    ->execute();
                Cli::write(Cli::color("Updated admin#{$id} ({$email})", 'green'));
            } else {
                $result = DB::insert('admin_users')->set([
                    'email' => $email,
                    'password' => $hash,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
                $id = (string)$result[0];
                Cli::write(Cli::color("Created admin#{$id} ({$email})", 'green'));
            }

            // Idempotent role grant.
            DB::delete('admin_user_roles')
                ->where('admin_user_id', $id)
                ->where('role', $role)
                ->execute();
            DB::insert('admin_user_roles')->set([
                'admin_user_id' => $id,
                'role' => $role,
                'granted_at' => $now,
            ])->execute();

            DB::commit_transaction();
            return Cli::color("Granted role '{$role}'. Password set.", 'green');
        } catch (\Throwable $e) {
            DB::rollback_transaction();
            return Cli::color('Seed failed: ' . $e->getMessage(), 'red');
        }
    }
}
