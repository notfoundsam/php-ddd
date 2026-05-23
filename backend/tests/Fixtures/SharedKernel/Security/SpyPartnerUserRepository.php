<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;

final class SpyPartnerUserRepository extends InMemoryUserRepository implements PartnerUserRepositoryInterface
{
}
