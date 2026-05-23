<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Partner\Infrastructure\Security;

use Audience\Partner\Application\Command\Auth\LogInCommand;
use Audience\Partner\Application\Command\Auth\LogOutCommand;
use Audience\Partner\Infrastructure\Security\PartnerSecurityConfig;
use PHPUnit\Framework\TestCase;

class PartnerSecurityConfigTest extends TestCase
{
    public function testLoginAndLogoutCommandsAreRegisteredAsPublic(): void
    {
        $config = new PartnerSecurityConfig();

        $commands = $config->getCommandPermissions();
        $this->assertArrayHasKey(LogInCommand::class, $commands);
        $this->assertArrayHasKey(LogOutCommand::class, $commands);
        $this->assertNull($commands[LogInCommand::class]);
        $this->assertNull($commands[LogOutCommand::class]);
    }

    public function testQueryPermissionsAreEmptyForNow(): void
    {
        $config = new PartnerSecurityConfig();

        $this->assertSame([], $config->getQueryPermissions());
    }

    public function testRolesAreDeclaredWithEmptyPermissionsForNow(): void
    {
        $config = new PartnerSecurityConfig();
        $roles = $config->getRolePermissions();

        $this->assertArrayHasKey(PartnerSecurityConfig::ROLE_OWNER, $roles);
        $this->assertArrayHasKey(PartnerSecurityConfig::ROLE_MEMBER, $roles);
        $this->assertSame([], $roles[PartnerSecurityConfig::ROLE_OWNER]);
        $this->assertSame([], $roles[PartnerSecurityConfig::ROLE_MEMBER]);
    }
}
