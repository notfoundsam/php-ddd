<?php

declare(strict_types=1);

namespace Audience\Partner\Infrastructure\Security;

use Audience\Partner\Application\Command\Auth\LogInCommand;
use Audience\Partner\Application\Command\Auth\LogOutCommand;
use SharedKernel\Domain\Security\SecurityConfigInterface;

final class PartnerSecurityConfig implements SecurityConfigInterface
{
    public const ROLE_OWNER = 'partner_owner';
    public const ROLE_MEMBER = 'partner_member';

    public function getCommandPermissions(): array
    {
        return [
            LogInCommand::class => null,
            LogOutCommand::class => null,
        ];
    }

    public function getQueryPermissions(): array
    {
        // TODO: register Partner queries here, e.g.
        //   GetLeadListQuery::class => 'partner.lead.list',
        return [];
    }

    public function getRolePermissions(): array
    {
        return [
            self::ROLE_OWNER => [],
            self::ROLE_MEMBER => [],
        ];
    }
}
