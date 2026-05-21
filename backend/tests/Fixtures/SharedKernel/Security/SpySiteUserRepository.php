<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;

final class SpySiteUserRepository extends InMemoryUserRepository implements SiteUserRepositoryInterface
{
}
