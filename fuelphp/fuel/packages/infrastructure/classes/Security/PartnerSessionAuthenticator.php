<?php

declare(strict_types=1);

namespace Infrastructure\Security;

use SharedKernel\Domain\Security\SessionAuthenticator\PartnerSessionAuthenticatorInterface;

final class PartnerSessionAuthenticator extends FuelPhpSessionAuthenticator implements PartnerSessionAuthenticatorInterface
{
    public function __construct()
    {
        parent::__construct('phpddd_partner');
    }
}
