<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\AuthenticatedUser;

final class SpyAdminRememberMeService implements AdminRememberMeServiceInterface
{
    public ?AuthenticatedUser $rememberedUser = null;

    public bool $forgotten = false;

    public function rememberUser(AuthenticatedUser $user): void
    {
        $this->rememberedUser = $user;
    }

    public function tryReanimate(): ?AuthenticatedUser
    {
        return null;
    }

    public function forget(): void
    {
        $this->forgotten = true;
    }

    public function forgetAllForUser(AuthenticatedUser $user): void
    {
    }
}
