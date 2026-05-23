<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use PHPUnit\Framework\TestCase;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\Throttle\ThrottleConfigResolverInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Throttle\Exceptions\ThrottleDriverException;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Domain\Throttle\Exceptions\ThrottleException;
use SharedKernel\Domain\Throttle\ThrottleConfig;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;
use SharedKernel\Domain\Throttle\ThrottleResolveResult;
use SharedKernel\Domain\Throttle\ThrottleInterface;
use SharedKernel\Domain\Throttle\ThrottleResult;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\CommandThrottleDecorator;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;
use Tests\Fixtures\SharedKernel\Http\StubRequestContext;
use Tests\Fixtures\SharedKernel\Security\StubSecurityContext;

class CommandThrottleDecoratorTest extends TestCase
{
    private function createConfig(): ThrottleConfig
    {
        return ThrottleConfig::fromArray([
            'warning_limit' => 20,
            'block_limit' => 30,
            'window' => 60,
        ]);
    }

    private function createDecorator(
        CommandBusInterface $inner,
        ThrottleConfigResolverInterface $throttleConfig,
        StubSecurityContext $securityContext,
        ?ThrottleFactoryInterface $throttleFactory = null,
        ?LoggerInterface $logger = null,
        ?StubRequestContext $requestContext = null
    ): CommandThrottleDecorator {
        return new CommandThrottleDecorator(
            $inner,
            $throttleFactory ?? $this->createMock(ThrottleFactoryInterface::class),
            $throttleConfig,
            $securityContext,
            $requestContext ?? new StubRequestContext('192.168.1.1'),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    public function testAuthenticatedUserAllowedDispatchesCommand(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $inner->expects($this->once())->method('dispatch');

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(ThrottleResolveResult::forDefault($this->createConfig()));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->method('attempt')->willReturn(ThrottleResult::allowed(5));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $user = new AuthenticatedUser('42', 'user@example.com', ['user'], UserType::CUSTOMER);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator($inner, $throttleConfig, $securityContext, $factory);
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testAuthenticatedUserBlockedThrowsException(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $inner->expects($this->never())->method('dispatch');

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(ThrottleResolveResult::forDefault($this->createConfig()));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->method('attempt')->willReturn(ThrottleResult::blocked(300));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $user = new AuthenticatedUser('42', 'user@example.com', ['user'], UserType::CUSTOMER);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator($inner, $throttleConfig, $securityContext, $factory);

        $this->expectException(ThrottleException::class);
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testAdminExemptDispatchesWithoutThrottle(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $inner->expects($this->once())->method('dispatch');

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(null);

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $user = new AuthenticatedUser('1', 'admin@example.com', ['admin'], UserType::ADMIN);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator($inner, $throttleConfig, $securityContext, $factory);
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testAnonymousUserAllowedDispatchesCommand(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $inner->expects($this->once())->method('dispatch');

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')
            ->with(TestCommand::class, 'command', null)
            ->willReturn(ThrottleResolveResult::forDefault($this->createConfig()));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->method('attempt')
            ->with('ip:10.0.0.1')
            ->willReturn(ThrottleResult::allowed(3));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $securityContext = new StubSecurityContext(null);
        $requestContext = new StubRequestContext('10.0.0.1');

        $decorator = $this->createDecorator(
            $inner,
            $throttleConfig,
            $securityContext,
            $factory,
            null,
            $requestContext
        );
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testAnonymousUserBlockedThrowsException(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $inner->expects($this->never())->method('dispatch');

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(ThrottleResolveResult::forDefault($this->createConfig()));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->method('attempt')->willReturn(ThrottleResult::blocked(600));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $securityContext = new StubSecurityContext(null);

        $decorator = $this->createDecorator($inner, $throttleConfig, $securityContext, $factory);

        $this->expectException(ThrottleException::class);
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testExcludedCommandDispatchesWithoutThrottle(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $inner->expects($this->once())->method('dispatch');

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(null);

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $securityContext = new StubSecurityContext(null);

        $decorator = $this->createDecorator($inner, $throttleConfig, $securityContext, $factory);
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testRedisConnectionFailureAllowsThrough(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $inner->expects($this->once())->method('dispatch');

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(ThrottleResolveResult::forDefault($this->createConfig()));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->method('attempt')->willThrowException(new ThrottleDriverException('Connection refused'));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $user = new AuthenticatedUser('42', 'user@example.com', ['user'], UserType::CUSTOMER);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator(
            $inner,
            $throttleConfig,
            $securityContext,
            $factory,
            $logger
        );
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testWarningThresholdLogsAndStillDispatches(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $inner->expects($this->once())->method('dispatch');

        $config = $this->createConfig();

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(ThrottleResolveResult::forDefault($config));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->method('attempt')->willReturn(ThrottleResult::warning($config->getWarningLimit()));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $user = new AuthenticatedUser('42', 'user@example.com', ['user'], UserType::CUSTOMER);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator(
            $inner,
            $throttleConfig,
            $securityContext,
            $factory,
            $logger
        );
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testResolvesIdentifierAsUserIdForAuthenticated(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')
            ->with(TestCommand::class, 'command', UserType::PARTNER)
            ->willReturn(ThrottleResolveResult::forDefault($this->createConfig()));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->expects($this->once())
            ->method('attempt')
            ->with('partner:99')
            ->willReturn(ThrottleResult::allowed(1));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $user = new AuthenticatedUser('99', 'partner@example.com', ['partner'], UserType::PARTNER);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator($inner, $throttleConfig, $securityContext, $factory);
        $decorator->dispatch(new TestCommand('test'));
    }
}
