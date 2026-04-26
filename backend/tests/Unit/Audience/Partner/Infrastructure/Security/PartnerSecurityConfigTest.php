<?php

declare(strict_types=1);

namespace Tests\Unit\Audience\Partner\Infrastructure\Security;

use Audience\Partner\Infrastructure\Security\PartnerSecurityConfig;
use PHPUnit\Framework\TestCase;

class PartnerSecurityConfigTest extends TestCase
{
    public function testCommandAndQueryPermissionsAreEmptyForNow(): void
    {
        $config = new PartnerSecurityConfig();

        $this->assertSame([], $config->getCommandPermissions());
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
