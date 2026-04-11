<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use Throwable;

final class QueryLoggerDecorator implements QueryBusInterface
{
    private QueryBusInterface $inner;
    private LoggerInterface $logger;
    private float $slowThresholdSeconds;

    public function __construct(
        QueryBusInterface $inner,
        LoggerInterface $logger,
        float $slowThresholdSeconds = 0.5
    ) {
        $this->inner = $inner;
        $this->logger = $logger;
        $this->slowThresholdSeconds = $slowThresholdSeconds;
    }

    /**
     * @template TResponse of QueryResponseInterface
     * @param QueryInterface<TResponse> $query
     * @return TResponse
     * @throws Throwable
     */
    public function dispatch(QueryInterface $query): QueryResponseInterface
    {
        $startTime = microtime(true);
        $queryName = get_class($query);

        try {
            $result = $this->inner->dispatch($query);

            $executionTimeMs = $this->calculateExecutionTimeMs($startTime);

            $logData = [
                'message_type' => 'query',
                'message_class' => $queryName,
                'success' => true,
                'execution_time_ms' => $executionTimeMs,
            ];

            if (microtime(true) - $startTime > $this->slowThresholdSeconds) {
                $this->logger->notice('Slow Query execution detected', $logData + [
                    'threshold_seconds' => $this->slowThresholdSeconds,
                ]);
            } else {
                $this->logger->debug('Query executed successfully', $logData);
            }

            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Query execution failed', [
                'message_type' => 'query',
                'message_class' => $queryName,
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
