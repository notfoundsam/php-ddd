<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\Exception\SecurityConfigurationException;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Infrastructure\Security\CompositeSecurityConfig;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\SecondTestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\SecondTestQuery;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQuery;
use Tests\Fixtures\SharedKernel\Security\CountingSecurityConfig;

class CompositeSecurityConfigTest extends TestCase
{
    public function testEmptyListProducesEmptyMaps(): void
    {
        $composite = new CompositeSecurityConfig([]);

        $this->assertSame([], $composite->getCommandPermissions());
        $this->assertSame([], $composite->getQueryPermissions());
        $this->assertSame([], $composite->getRolePermissions());
    }

    public function testSingleConfigPassesThrough(): void
    {
        $config = $this->configWith(
            [TestCommand::class => 'a.do'],
            [TestQuery::class => 'a.read'],
            ['viewer' => ['a.read']]
        );
        $composite = new CompositeSecurityConfig([$config]);

        $this->assertSame([TestCommand::class => 'a.do'], $composite->getCommandPermissions());
        $this->assertSame([TestQuery::class => 'a.read'], $composite->getQueryPermissions());
        $this->assertSame(['viewer' => ['a.read']], $composite->getRolePermissions());
    }

    public function testDisjointConfigsAreMerged(): void
    {
        $a = $this->configWith(
            [TestCommand::class => 'a.do'],
            [TestQuery::class => 'a.read'],
            ['viewer' => ['user.view']]
        );
        $b = $this->configWith(
            [SecondTestCommand::class => 'b.do'],
            [SecondTestQuery::class => 'b.read'],
            ['editor' => ['user.create']]
        );
        $composite = new CompositeSecurityConfig([$a, $b]);

        $this->assertSame(
            [TestCommand::class => 'a.do', SecondTestCommand::class => 'b.do'],
            $composite->getCommandPermissions()
        );
        $this->assertSame(
            [TestQuery::class => 'a.read', SecondTestQuery::class => 'b.read'],
            $composite->getQueryPermissions()
        );
        $this->assertSame(
            ['viewer' => ['user.view'], 'editor' => ['user.create']],
            $composite->getRolePermissions()
        );
    }

    public function testNullPermissionEntryIsPreservedThroughMerge(): void
    {
        $a = $this->configWith([TestCommand::class => null], [], []);
        $b = $this->configWith([SecondTestCommand::class => 'other.do'], [], []);
        $composite = new CompositeSecurityConfig([$a, $b]);

        $merged = $composite->getCommandPermissions();
        $this->assertArrayHasKey(TestCommand::class, $merged);
        $this->assertNull($merged[TestCommand::class]);
        $this->assertSame('other.do', $merged[SecondTestCommand::class]);
    }

    public function testDuplicateCommandKeyThrows(): void
    {
        $a = $this->configWith([TestCommand::class => 'a.do'], [], []);
        $b = $this->configWith([TestCommand::class => 'b.do'], [], []);
        $composite = new CompositeSecurityConfig([$a, $b]);

        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessageMatches('/Duplicate.*TestCommand/');
        $composite->getCommandPermissions();
    }

    public function testDuplicateQueryKeyThrows(): void
    {
        $a = $this->configWith([], [TestQuery::class => 'a.read'], []);
        $b = $this->configWith([], [TestQuery::class => 'b.read'], []);
        $composite = new CompositeSecurityConfig([$a, $b]);

        $this->expectException(SecurityConfigurationException::class);
        $this->expectExceptionMessageMatches('/Duplicate.*TestQuery/');
        $composite->getQueryPermissions();
    }

    public function testRolePermissionsUnionAndDeduplicate(): void
    {
        $a = $this->configWith([], [], ['admin' => ['user.create', 'user.view']]);
        $b = $this->configWith([], [], ['admin' => ['user.view', 'user.delete']]);
        $composite = new CompositeSecurityConfig([$a, $b]);

        $roles = $composite->getRolePermissions();

        $this->assertArrayHasKey('admin', $roles);
        sort($roles['admin']);
        $this->assertSame(['user.create', 'user.delete', 'user.view'], $roles['admin']);
    }

    public function testResultsAreCachedAcrossCalls(): void
    {
        $config = new CountingSecurityConfig();
        $composite = new CompositeSecurityConfig([$config]);

        $composite->getCommandPermissions();
        $composite->getCommandPermissions();
        $composite->getQueryPermissions();
        $composite->getQueryPermissions();
        $composite->getRolePermissions();
        $composite->getRolePermissions();

        $this->assertSame(1, $config->commandCalls);
        $this->assertSame(1, $config->queryCalls);
        $this->assertSame(1, $config->roleCalls);
    }

    /**
     * @param array<class-string, string|null> $commands
     * @param array<class-string, string|null> $queries
     * @param array<string, array<string>> $roles
     */
    private function configWith(array $commands, array $queries, array $roles): SecurityConfigInterface
    {
        return new class ($commands, $queries, $roles) implements SecurityConfigInterface {
            /** @var array<class-string, string|null> */
            private array $commands;
            /** @var array<class-string, string|null> */
            private array $queries;
            /** @var array<string, array<string>> */
            private array $roles;

            /**
             * @param array<class-string, string|null> $commands
             * @param array<class-string, string|null> $queries
             * @param array<string, array<string>> $roles
             */
            public function __construct(array $commands, array $queries, array $roles)
            {
                $this->commands = $commands;
                $this->queries = $queries;
                $this->roles = $roles;
            }

            public function getCommandPermissions(): array
            {
                return $this->commands;
            }

            public function getQueryPermissions(): array
            {
                return $this->queries;
            }

            public function getRolePermissions(): array
            {
                return $this->roles;
            }
        };
    }
}
