<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus;

use PHPUnit\Framework\TestCase;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandBusFactory;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandHandlerRegistryInterface;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\InMemoryContainer;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommandHandler;

class CommandBusFactoryTest extends TestCase
{
    public function testFactoryComposesMultipleRegistries(): void
    {
        $handler = new TestCommandHandler();

        $container = new InMemoryContainer([
            TestCommandHandler::class => $handler,
        ]);

        $registryA = new class implements CommandHandlerRegistryInterface {
            public function getCommandHandlers(): array
            {
                return [];
            }
        };

        $registryB = new class implements CommandHandlerRegistryInterface {
            public function getCommandHandlers(): array
            {
                return [TestCommand::class => TestCommandHandler::class];
            }
        };

        $factory = new CommandBusFactory($container, [$registryA, $registryB]);
        $bus = $factory();

        $command = new TestCommand('test-value');
        $bus->dispatch($command);

        $this->assertCount(1, $handler->getHandled());
        $this->assertSame($command, $handler->getHandled()[0]);
    }
}
