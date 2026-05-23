<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;
use SharedKernel\Domain\Security\UserType;

// Site audience maps to the customer_users table and UserType::CUSTOMER —
// audience name ('site') differs from user-type ('customer') by design (ADR-014).
final class SiteUserRepository extends FuelPhpUserRepository implements SiteUserRepositoryInterface
{
    public function __construct()
    {
        parent::__construct('customer_users', 'customer_user_roles', 'customer_user_id', UserType::CUSTOMER);
    }
}
