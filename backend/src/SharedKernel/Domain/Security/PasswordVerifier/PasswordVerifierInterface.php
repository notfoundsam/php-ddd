<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\PasswordVerifier;

use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\ValueObjects\EmailAddress;

interface PasswordVerifierInterface
{
    /**
     * Returns the authenticated user on success, null on any failure
     * (user not found, wrong password).
     *
     * Implementations MUST execute in roughly constant time regardless of whether
     * the user exists, to prevent username enumeration via timing analysis.
     */
    public function verify(EmailAddress $email, PlaintextPassword $password): ?AuthenticatedUser;
}
