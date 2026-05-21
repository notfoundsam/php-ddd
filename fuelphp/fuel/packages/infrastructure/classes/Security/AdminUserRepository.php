<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\UserType;

final class AdminUserRepository extends FuelPhpUserRepository implements AdminUserRepositoryInterface
{
    public function __construct()
    {
        parent::__construct('admin_users', 'admin_user_roles', 'admin_user_id', UserType::ADMIN);
    }
}
