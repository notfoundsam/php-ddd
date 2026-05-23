<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandBus;
use SharedKernel\Infrastructure\CqrsMessageBus\HandlerNotFoundException;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\InMemoryContainer;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommandHandler;

class CommandBusTest extends TestCase
{
    public function testDispatchInvokesRegisteredHandler(): void
    {
        $handler = new TestCommandHandler();
        $container = new InMemoryContainer([TestCommandHandler::class => $handler]);

        $bus = new CommandBus($container);
        $bus->register(TestCommand::class, TestCommandHandler::class);

        $command = new TestCommand('test-value');
        $bus->dispatch($command);

        $this->assertCount(1, $handler->getHandled());
        $this->assertSame($command, $handler->getHandled()[0]);
    }

    public function testRegisterDoesNotResolveHandlerEagerly(): void
    {
        // Empty container — if register() tried to resolve, this would throw.
        $bus = new CommandBus(new InMemoryContainer([]));
        $bus->register(TestCommand::class, TestCommandHandler::class);

        $this->expectNotToPerformAssertions();
    }

    public function testDispatchThrowsForUnregisteredCommand(): void
    {
        $bus = new CommandBus(new InMemoryContainer([]));

        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionMessage(TestCommand::class);

        $bus->dispatch(new TestCommand('test-value'));
    }
}
