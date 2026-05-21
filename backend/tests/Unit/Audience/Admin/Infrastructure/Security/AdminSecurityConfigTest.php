<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Admin\Infrastructure\Security;

use Audience\Admin\Application\Command\Auth\LogInCommand;
use Audience\Admin\Application\Command\Auth\LogOutCommand;
use Audience\Admin\Infrastructure\Security\AdminSecurityConfig;
use PHPUnit\Framework\TestCase;

class AdminSecurityConfigTest extends TestCase
{
    public function testLoginAndLogoutCommandsAreRegisteredAsPublic(): void
    {
        $config = new AdminSecurityConfig();

        $commands = $config->getCommandPermissions();
        $this->assertArrayHasKey(LogInCommand::class, $commands);
        $this->assertArrayHasKey(LogOutCommand::class, $commands);
        $this->assertNull($commands[LogInCommand::class]);
        $this->assertNull($commands[LogOutCommand::class]);
    }

    public function testQueryPermissionsAreEmptyForNow(): void
    {
        $config = new AdminSecurityConfig();

        $this->assertSame([], $config->getQueryPermissions());
    }

    public function testAdminRoleHasWildcardPermission(): void
    {
        $config = new AdminSecurityConfig();
        $roles = $config->getRolePermissions();

        $this->assertSame(['*'], $roles[AdminSecurityConfig::ROLE_ADMIN]);
    }

    public function testStaffOutsourcerAndStaffInvoiceRolesExistWithEmptyPermissionsForNow(): void
    {
        $config = new AdminSecurityConfig();
        $roles = $config->getRolePermissions();

        $this->assertArrayHasKey(AdminSecurityConfig::ROLE_STAFF, $roles);
        $this->assertArrayHasKey(AdminSecurityConfig::ROLE_OUTSOURCER, $roles);
        $this->assertArrayHasKey(AdminSecurityConfig::ROLE_STAFF_INVOICE, $roles);
        $this->assertSame([], $roles[AdminSecurityConfig::ROLE_STAFF]);
        $this->assertSame([], $roles[AdminSecurityConfig::ROLE_OUTSOURCER]);
        $this->assertSame([], $roles[AdminSecurityConfig::ROLE_STAFF_INVOICE]);
    }
}
