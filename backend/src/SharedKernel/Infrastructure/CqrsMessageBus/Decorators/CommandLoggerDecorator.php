<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use Throwable;

final class CommandLoggerDecorator implements CommandBusInterface
{
    private CommandBusInterface $inner;
    private LoggerInterface $logger;
    private float $slowThresholdSeconds;

    public function __construct(
        CommandBusInterface $inner,
        LoggerInterface $logger,
        float $slowThresholdSeconds = 2.0
    ) {
        $this->inner = $inner;
        $this->logger = $logger;
        $this->slowThresholdSeconds = $slowThresholdSeconds;
    }

    /**
     * @throws Throwable
     */
    public function dispatch(CommandInterface $command): void
    {
        $startTime = microtime(true);
        $commandName = get_class($command);

        try {
            $this->inner->dispatch($command);

            $executionTimeMs = $this->calculateExecutionTimeMs($startTime);

            $logData = [
                'message_type' => 'command',
                'message_class' => $commandName,
                'success' => true,
                'execution_time_ms' => $executionTimeMs,
            ];

            if (microtime(true) - $startTime > $this->slowThresholdSeconds) {
                $this->logger->warning('Slow Command execution detected', $logData + [
                    'threshold_seconds' => $this->slowThresholdSeconds,
                ]);
            } else {
                $this->logger->info('Command executed successfully', $logData);
            }
        } catch (Throwable $e) {
            $this->logger->error('Command execution failed', [
                'message_type' => 'command',
                'message_class' => $commandName,
                'success' => false,
                'execution_time_ms' => $this->calculateExecutionTimeMs($startTime),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    private function calculateExecutionTimeMs(float $startTime): float
    {
        return round((microtime(true) - $startTime) * 1000, 2);
    }
}
