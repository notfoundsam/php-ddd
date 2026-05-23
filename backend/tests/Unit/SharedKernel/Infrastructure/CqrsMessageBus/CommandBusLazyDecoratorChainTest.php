<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus;

use PHPUnit\Framework\TestCase;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\Throttle\ThrottleConfigResolverInterface;
use SharedKernel\Domain\EventSystem\DomainEventCollectorInterface;
use SharedKernel\Domain\EventSystem\OutboxEventProcessorInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Security\AuthorizationServiceInterface;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandBus;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\CommandLoggerDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\CommandThrottleDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\CommandTransactionDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\SecurityCommandDecorator;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\SpyTransactionManager;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommandHandler;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestLog;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TrackingContainer;
use Tests\Fixtures\SharedKernel\Http\StubRequestContext;
use Tests\Fixtures\SharedKernel\Security\StubSecurityContext;

/**
 * End-to-end smoke test: lazy CommandBus wrapped in the full production decorator
 * chain (Throttle → Security → Logger → Transaction → Handler).
 *
 * Locks down two regressions:
 *  1. Composing decorators around a lazy bus must still dispatch successfully.
 *  2. Handler resolution must happen at dispatch time (after Transaction begins),
 *     not at bus construction. A tracking container catches both cases.
 */
final class CommandBusLazyDecoratorChainTest extends TestCase
{
    public function testFullChainDispatchesAndResolvesHandlerLazilyInsideTransaction(): void
    {
        $handler = new TestCommandHandler();

        $log = new TestLog();
        $container = new TrackingContainer([TestCommandHandler::class => $handler], $log);

        $bus = new CommandBus($container);
        $bus->register(TestCommand::class, TestCommandHandler::class);

        $this->assertSame(
            [],
            $log->all(),
            'Building bus + registering handler must not touch the container'
        );

        $txManager = new SpyTransactionManager($log);
        $outbox = $this->createMock(OutboxEventProcessorInterface::class);
        $outbox->expects($this->once())->method('storeBatch');

        $eventCollector = $this->createMock(DomainEventCollectorInterface::class);
        $eventCollector->method('pull')->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $securityConfig = $this->createMock(SecurityConfigInterface::class);
        $securityConfig->method('getCommandPermissions')->willReturn([TestCommand::class => null]);

        $throttleResolver = $this->createMock(ThrottleConfigResolverInterface::class);
        $throttleResolver->method('resolve')->willReturn(null);

        $throttleFactory = $this->createMock(ThrottleFactoryInterface::class);
        $throttleFactory->expects($this->never())->method('create');

        $decorated = new CommandTransactionDecorator($bus, $eventCollector, $txManager, $outbox);
        $decorated = new CommandLoggerDecorator($decorated, $logger);
        $decorated = new SecurityCommandDecorator(
            $decorated,
            new StubSecurityContext(null),
            $this->createMock(AuthorizationServiceInterface::class),
            $securityConfig
        );
        $decorated = new CommandThrottleDecorator(
            $decorated,
            $throttleFactory,
            $throttleResolver,
            new StubSecurityContext(null),
            new StubRequestContext('10.0.0.1'),
            $logger
        );

        $this->assertInstanceOf(CommandBusInterface::class, $decorated);
        $this->assertSame(
            [],
            $log->all(),
            'Composing decorators must not touch the container or the transaction manager'
        );

        $decorated->dispatch(new TestCommand('payload'));

        $this->assertSame(
            ['begin', 'resolve:' . TestCommandHandler::class, 'commit'],
            $log->all(),
            'Handler must resolve AFTER beginTransaction and BEFORE commit'
        );

        $this->assertCount(1, $handler->getHandled());
    }
}
