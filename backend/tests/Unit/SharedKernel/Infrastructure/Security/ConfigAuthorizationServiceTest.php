<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Infrastructure\Security\ConfigAuthorizationService;

class ConfigAuthorizationServiceTest extends TestCase
{
    public function testUserWithNoRolesIsDenied(): void
    {
        $service = new ConfigAuthorizationService($this->configWithRoles([]));
        $user = new AuthenticatedUser('1', 'a@example.com', []);

        $this->assertFalse($service->isAllowed($user, 'user.create'));
    }

    public function testRoleGrantingExactPermissionAllows(): void
    {
        $service = new ConfigAuthorizationService($this->configWithRoles([
            'editor' => ['user.create', 'user.view'],
        ]));
        $user = new AuthenticatedUser('1', 'a@example.com', ['editor']);

        $this->assertTrue($service->isAllowed($user, 'user.create'));
        $this->assertTrue($service->isAllowed($user, 'user.view'));
        $this->assertFalse($service->isAllowed($user, 'user.delete'));
    }

    public function testWildcardRoleAllowsAnyPermission(): void
    {
        $service = new ConfigAuthorizationService($this->configWithRoles([
            'super_admin' => ['*'],
        ]));
        $user = new AuthenticatedUser('1', 'a@example.com', ['super_admin']);

        $this->assertTrue($service->isAllowed($user, 'user.create'));
        $this->assertTrue($service->isAllowed($user, 'anything.you.want'));
    }

    public function testMultipleRolesAggregatePermissions(): void
    {
        $service = new ConfigAuthorizationService($this->configWithRoles([
            'viewer' => ['user.view'],
            'editor' => ['user.create'],
        ]));
        $user = new AuthenticatedUser('1', 'a@example.com', ['viewer', 'editor']);

        $this->assertTrue($service->isAllowed($user, 'user.view'));
        $this->assertTrue($service->isAllowed($user, 'user.create'));
        $this->assertFalse($service->isAllowed($user, 'user.delete'));
    }

    public function testUnknownRoleIsSilentlyIgnored(): void
    {
        $service = new ConfigAuthorizationService($this->configWithRoles([
            'viewer' => ['user.view'],
        ]));
        $user = new AuthenticatedUser('1', 'a@example.com', ['phantom_role']);

        $this->assertFalse($service->isAllowed($user, 'user.view'));
    }

    public function testWildcardOnOneRoleStillAllowsWhenOtherRolesPresent(): void
    {
        $service = new ConfigAuthorizationService($this->configWithRoles([
            'viewer' => ['user.view'],
            'system' => ['*'],
        ]));
        $user = new AuthenticatedUser('1', 'a@example.com', ['viewer', 'system']);

        $this->assertTrue($service->isAllowed($user, 'something.exotic'));
    }

    /**
     * @param array<string, array<string>> $roles
     */
    private function configWithRoles(array $roles): SecurityConfigInterface
    {
        return new class ($roles) implements SecurityConfigInterface {
            /** @var array<string, array<string>> */
            private array $roles;

            /**
             * @param array<string, array<string>> $roles
             */
            public function __construct(array $roles)
            {
                $this->roles = $roles;
            }

            public function getCommandPermissions(): array
            {
                return [];
            }

            public function getQueryPermissions(): array
            {
                return [];
            }

            public function getRolePermissions(): array
            {
                return $this->roles;
            }
        };
    }
}
