<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;
use SharedKernel\Domain\Security\UserType;

final class PartnerUserRepository extends FuelPhpUserRepository implements PartnerUserRepositoryInterface
{
    public function __construct()
    {
        parent::__construct('partner_users', 'partner_user_roles', 'partner_user_id', UserType::PARTNER);
    }
}
