<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use PHPUnit\Framework\TestCase;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Application\Throttle\ThrottleConfigResolverInterface;
use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
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
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\QueryThrottleDecorator;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQuery;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQueryResponse;
use Tests\Fixtures\SharedKernel\Http\StubRequestContext;
use Tests\Fixtures\SharedKernel\Security\StubSecurityContext;

class QueryThrottleDecoratorTest extends TestCase
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
        QueryBusInterface $inner,
        ThrottleConfigResolverInterface $throttleConfig,
        StubSecurityContext $securityContext,
        ?ThrottleFactoryInterface $throttleFactory = null,
        ?AsyncEventProcessorInterface $asyncEventProcessor = null,
        ?LoggerInterface $logger = null,
        ?StubRequestContext $requestContext = null
    ): QueryThrottleDecorator {
        return new QueryThrottleDecorator(
            $inner,
            $throttleFactory ?? $this->createMock(ThrottleFactoryInterface::class),
            $throttleConfig,
            $securityContext,
            $requestContext ?? new StubRequestContext('192.168.1.1'),
            $asyncEventProcessor ?? $this->createMock(AsyncEventProcessorInterface::class),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    private function createInner(?TestQueryResponse $response = null): QueryBusInterface
    {
        $inner = $this->createMock(QueryBusInterface::class);
        $inner->method('dispatch')->willReturn($response ?? new TestQueryResponse('result'));

        return $inner;
    }

    public function testAuthenticatedUserAllowedReturnsResponse(): void
    {
        $expectedResponse = new TestQueryResponse('data');
        $inner = $this->createMock(QueryBusInterface::class);
        $inner->expects($this->once())->method('dispatch')->willReturn($expectedResponse);

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(ThrottleResolveResult::forDefault($this->createConfig()));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->method('attempt')->willReturn(ThrottleResult::allowed(5));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $user = new AuthenticatedUser('42', 'user@example.com', ['user'], UserType::CUSTOMER);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator($inner, $throttleConfig, $securityContext, $factory);
        $result = $decorator->dispatch(new TestQuery('1'));

        $this->assertSame($expectedResponse, $result);
    }

    public function testAuthenticatedUserBlockedThrowsException(): void
    {
        $inner = $this->createMock(QueryBusInterface::class);
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
        $decorator->dispatch(new TestQuery('1'));
    }

    public function testAdminExemptReturnsResponse(): void
    {
        $inner = $this->createInner();

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(null);

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $user = new AuthenticatedUser('1', 'admin@example.com', ['admin'], UserType::ADMIN);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator($inner, $throttleConfig, $securityContext, $factory);
        $result = $decorator->dispatch(new TestQuery('1'));

        $this->assertInstanceOf(TestQueryResponse::class, $result);
    }

    public function testAnonymousUserAllowedReturnsResponse(): void
    {
        $inner = $this->createInner();

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')
            ->with(TestQuery::class, 'query', null)
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
            null,
            $requestContext
        );
        $result = $decorator->dispatch(new TestQuery('1'));

        $this->assertInstanceOf(TestQueryResponse::class, $result);
    }

    public function testAnonymousUserBlockedThrowsException(): void
    {
        $inner = $this->createMock(QueryBusInterface::class);
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
        $decorator->dispatch(new TestQuery('1'));
    }

    public function testRedisConnectionFailureAllowsThrough(): void
    {
        $inner = $this->createInner();

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
            null,
            $logger
        );
        $result = $decorator->dispatch(new TestQuery('1'));

        $this->assertInstanceOf(TestQueryResponse::class, $result);
    }

    public function testWarningThresholdFiresEventAndStillReturnsResponse(): void
    {
        $inner = $this->createInner();
        $config = $this->createConfig();

        $throttleConfig = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleConfig->method('resolve')->willReturn(ThrottleResolveResult::forDefault($config));

        $throttler = $this->createMock(ThrottleInterface::class);
        $throttler->method('attempt')->willReturn(ThrottleResult::warning($config->getWarningLimit()));

        $factory = $this->createMock(ThrottleFactoryInterface::class);
        $factory->method('create')->willReturn($throttler);

        $asyncProcessor = $this->createMock(AsyncEventProcessorInterface::class);
        $asyncProcessor->expects($this->once())->method('store');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $user = new AuthenticatedUser('42', 'user@example.com', ['user'], UserType::CUSTOMER);
        $securityContext = new StubSecurityContext($user);

        $decorator = $this->createDecorator(
            $inner,
            $throttleConfig,
            $securityContext,
            $factory,
            $asyncProcessor,
            $logger
        );
        $result = $decorator->dispatch(new TestQuery('1'));

        $this->assertInstanceOf(TestQueryResponse::class, $result);
    }
}
