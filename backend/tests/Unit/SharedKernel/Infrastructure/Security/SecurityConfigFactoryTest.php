<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Infrastructure\Security\CompositeSecurityConfig;
use SharedKernel\Infrastructure\Security\SecurityConfigFactory;
use SharedKernel\Domain\Security\SecurityConfigRegistryInterface;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\SecondTestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\ThirdTestCommand;

class SecurityConfigFactoryTest extends TestCase
{
    public function testInvokeReturnsCompositeWithNoRegistries(): void
    {
        $factory = new SecurityConfigFactory([]);
        $result = $factory();

        $this->assertInstanceOf(CompositeSecurityConfig::class, $result);
        $this->assertSame([], $result->getCommandPermissions());
    }

    public function testInvokeAggregatesAllConfigsFromAllRegistries(): void
    {
        $configA = $this->config([TestCommand::class => 'a.do'], []);
        $configB = $this->config([SecondTestCommand::class => 'b.do'], []);
        $configC = $this->config([ThirdTestCommand::class => 'c.do'], ['admin' => ['c.do']]);

        $registry1 = $this->registry([$configA, $configB]);
        $registry2 = $this->registry([$configC]);

        $factory = new SecurityConfigFactory([$registry1, $registry2]);
        $result = $factory();

        $this->assertSame(
            [
                TestCommand::class => 'a.do',
                SecondTestCommand::class => 'b.do',
                ThirdTestCommand::class => 'c.do',
            ],
            $result->getCommandPermissions()
        );
        $this->assertSame(['admin' => ['c.do']], $result->getRolePermissions());
    }

    /**
     * @param array<class-string, string|null> $commands
     * @param array<string, array<string>> $roles
     */
    private function config(array $commands, array $roles): SecurityConfigInterface
    {
        return new class ($commands, $roles) implements SecurityConfigInterface {
            /** @var array<class-string, string|null> */
            private array $commands;
            /** @var array<string, array<string>> */
            private array $roles;

            /**
             * @param array<class-string, string|null> $commands
             * @param array<string, array<string>> $roles
             */
            public function __construct(array $commands, array $roles)
            {
                $this->commands = $commands;
                $this->roles = $roles;
            }

            public function getCommandPermissions(): array
            {
                return $this->commands;
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

    /**
     * @param SecurityConfigInterface[] $configs
     */
    private function registry(array $configs): SecurityConfigRegistryInterface
    {
        return new class ($configs) implements SecurityConfigRegistryInterface {
            /** @var SecurityConfigInterface[] */
            private array $configs;

            /**
             * @param SecurityConfigInterface[] $configs
             */
            public function __construct(array $configs)
            {
                $this->configs = $configs;
            }

            public function getSecurityConfigs(): array
            {
                return $this->configs;
            }
        };
    }
}
