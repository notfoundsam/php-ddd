<?php

namespace Fuel\Tasks;

use Container;
use Fuel\Core\Database_Connection;
use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
use SharedKernel\Domain\EventSystem\EventProcessorInterface;
use SharedKernel\Domain\EventSystem\OutboxEventProcessorInterface;
use SharedKernel\Domain\EventSystem\ScheduledEventProcessorInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use Throwable;

class worker
{
    private const IDLE_POLL_SECONDS = 5;
    private const ERROR_BACKOFF_SECONDS = 5;
    private bool $shouldStop = false;
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = Container::logger();
        $this->registerSignalHandlers();
    }

    /**
     * Outbox event worker - processes outbox events from database
     * Run with: php oil r worker:outbox
     */
    public function outbox()
    {
        $processor = Container::resolve(OutboxEventProcessorInterface::class);
        $this->runWorkerLoop('Outbox', $processor);
    }

    /**
     * Scheduled event worker - processes scheduled events from database at their scheduled time
     * Run with: php oil r worker:scheduled
     */
    public function scheduled()
    {
        $processor = Container::resolve(ScheduledEventProcessorInterface::class);
        $this->runWorkerLoop('Scheduled', $processor);
    }

    /**
     * Async event worker - processes async events from SQS/ElasticMQ
     * Run with: php oil r worker:async
     */
    public function async()
    {
        $processor = Container::resolve(AsyncEventProcessorInterface::class);

        $this->logger->notice('Async worker started');

        while (true) {
            if ($this->shouldStop) {
                $this->logger->notice('Async worker received shutdown signal, exiting gracefully...');
                break;
            }

            try {
                $processor->processEvents();
            } catch (Throwable $e) {
                $this->logger->critical('Async processing failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                sleep(self::ERROR_BACKOFF_SECONDS);
            }
        }

        $this->logger->notice('Async worker stopped');
    }

    /**
     * Worker loop for database-backed event processors (outbox, scheduled)
     *
     * Sleeps when idle to avoid unnecessary DB polling.
     * Disconnects database after each iteration to prevent stale connections.
     */
    private function runWorkerLoop(string $workerName, EventProcessorInterface $processor): void
    {
        $this->logger->notice("$workerName worker started");

        while (true) {
            if ($this->shouldStop) {
                $this->logger->notice("$workerName worker received shutdown signal, exiting gracefully...");
                break;
            }

            try {
                $retrieved = $processor->processEvents();

                if ($retrieved === 0) {
                    sleep(self::IDLE_POLL_SECONDS);
                }
            } catch (Throwable $e) {
                $this->logger->critical("$workerName processing failed", [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                sleep(self::ERROR_BACKOFF_SECONDS);
            } finally {
                Database_Connection::instance()->disconnect();
            }
        }

        $this->logger->notice("$workerName worker stopped");
    }

    /**
     * Register signal handlers for graceful shutdown
     */
    private function registerSignalHandlers(): void
    {
        // Enable async signal handling - signals are processed immediately when received
        pcntl_async_signals(true);

        // Handle SIGTERM (sent by ECS when stopping containers)
        pcntl_signal(SIGTERM, function () {
            $this->logger->notice('SIGTERM received');
            $this->shouldStop = true;
        });

        // Handle SIGINT (Ctrl+C for local testing)
        pcntl_signal(SIGINT, function () {
            $this->logger->notice('SIGINT received');
            $this->shouldStop = true;
        });

        // Handle SIGQUIT (might be sent by init processes)
        pcntl_signal(SIGQUIT, function () {
            $this->logger->notice('SIGQUIT received');
            $this->shouldStop = true;
        });
    }
}
