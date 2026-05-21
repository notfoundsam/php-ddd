<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use SharedKernel\Domain\Security\SessionAuthenticator\AdminSessionAuthenticatorInterface;

final class AdminSessionAuthenticator extends FuelPhpSessionAuthenticator implements AdminSessionAuthenticatorInterface
{
    public function __construct()
    {
        parent::__construct('phpddd_admin');
    }
}
