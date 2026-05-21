<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\PasswordVerifier\SitePasswordVerifierInterface;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\ValueObjects\EmailAddress;

final class StubSitePasswordVerifier implements SitePasswordVerifierInterface
{
    private ?AuthenticatedUser $result;

    public function __construct(?AuthenticatedUser $result)
    {
        $this->result = $result;
    }

    public function verify(EmailAddress $email, PlaintextPassword $password): ?AuthenticatedUser
    {
        return $this->result;
    }
}
