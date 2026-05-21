<?php

declare(strict_types=1);

namespace Audience\Admin\Infrastructure\Security;

use Audience\Admin\Application\Command\Auth\LogInCommand;
use Audience\Admin\Application\Command\Auth\LogOutCommand;
use SharedKernel\Domain\Security\SecurityConfigInterface;

final class AdminSecurityConfig implements SecurityConfigInterface
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_OUTSOURCER = 'outsourcer';
    public const ROLE_STAFF_INVOICE = 'staff_invoice';

    public function getCommandPermissions(): array
    {
        return [
            LogInCommand::class => null,
            LogOutCommand::class => null,
        ];
    }

    public function getQueryPermissions(): array
    {
        // TODO: register Admin queries here, e.g.
        //   GetInvoiceQuery::class => 'admin.invoice.view',
        return [];
    }

    public function getRolePermissions(): array
    {
        return [
            self::ROLE_ADMIN => ['*'],
            self::ROLE_STAFF => [],
            self::ROLE_OUTSOURCER => [],
            self::ROLE_STAFF_INVOICE => [],
        ];
    }
}
