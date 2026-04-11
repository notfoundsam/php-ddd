<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandBus;
use SharedKernel\Infrastructure\CqrsMessageBus\HandlerNotFoundException;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommandHandler;

class CommandBusTest extends TestCase
{
    public function testDispatchInvokesRegisteredHandler(): void
    {
        $handler = new TestCommandHandler();
        $bus = new CommandBus();
        $bus->register(TestCommand::class, $handler);

        $command = new TestCommand('test-value');
        $bus->dispatch($command);

        $this->assertCount(1, $handler->getHandled());
        $this->assertSame($command, $handler->getHandled()[0]);
    }

    public function testDispatchThrowsForUnregisteredCommand(): void
    {
        $bus = new CommandBus();

        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionMessage(TestCommand::class);

        $bus->dispatch(new TestCommand('test-value'));
    }
}
