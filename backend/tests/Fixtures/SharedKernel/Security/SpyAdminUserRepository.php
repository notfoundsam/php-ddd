<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;

final class SpyAdminUserRepository extends InMemoryUserRepository implements AdminUserRepositoryInterface
{
}
