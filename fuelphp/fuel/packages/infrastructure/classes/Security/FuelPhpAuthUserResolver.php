<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use Auth;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserResolverInterface;
use SharedKernel\Domain\Security\UserType;

// Reads the currently logged-in user from FuelPHP Auth (SimpleAuth driver).
// Roles are the string names declared in `simpleauth.groups[<id>].roles` config —
// these are the same strings that SecurityConfigInterface::getRolePermissions() keys on.
final class FuelPhpAuthUserResolver implements UserResolverInterface
{
    public function resolve(): ?AuthenticatedUser
    {
        if (!Auth::check()) {
            return null;
        }

        $instance = Auth::instance();
        if ($instance === false) {
            return null;
        }

        $userId = $instance->get_user_id();
        if (!is_array($userId) || !isset($userId[1])) {
            return null;
        }

        $email = $instance->get_email();
        $roles = Auth::group()->get_roles();
        if (!is_array($roles)) {
            $roles = [];
        }

        return new AuthenticatedUser(
            (string) $userId[1],
            is_string($email) ? $email : '',
            array_values(array_filter($roles, 'is_string')),
            UserType::PARTNER
        );
    }
}
