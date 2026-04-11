<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\CommandLoggerDecorator;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;

class CommandLoggerDecoratorTest extends TestCase
{
    public function testLogsSuccessfulCommandAtInfoLevel(): void
    {
        $inner = $this->createMock(CommandBusInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Command executed successfully',
                $this->callback(function (array $context): bool {
                    return $context['message_type'] === 'command'
                        && $context['message_class'] === TestCommand::class
                        && $context['success'] === true
                        && isset($context['execution_time_ms']);
                })
            );

        $decorator = new CommandLoggerDecorator($inner, $logger);
        $decorator->dispatch(new TestCommand('test'));
    }

    public function testLogsErrorAndRethrowsException(): void
    {
        $exception = new RuntimeException('Something broke');

        $inner = $this->createMock(CommandBusInterface::class);
        $inner->method('dispatch')
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Command execution failed',
                $this->callback(function (array $context): bool {
                    return $context['success'] === false
                        && $context['error'] === 'Something broke'
                        && $context['error_class'] === RuntimeException::class;
                })
            );

        $decorator = new CommandLoggerDecorator($inner, $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something broke');

        $decorator->dispatch(new TestCommand('test'));
    }
}
