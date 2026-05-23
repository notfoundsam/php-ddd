<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use SharedKernel\Domain\Security\SessionAuthenticator\SiteSessionAuthenticatorInterface;

final class SiteSessionAuthenticator extends FuelPhpSessionAuthenticator implements SiteSessionAuthenticatorInterface
{
    public function __construct()
    {
        parent::__construct('phpddd');
    }
}
