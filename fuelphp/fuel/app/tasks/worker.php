<?php

namespace Fuel\Tasks;

use Container;
use SharedKernel\Domain\Logger\LoggerInterface;

class worker
{
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
        $this->logger->notice('Outbox worker started');

        $iteration = 0;

        while (true) {
            if ($this->shouldStop) {
                $this->logger->notice('Outbox worker received shutdown signal, exiting gracefully...');
                break;
            }

            $iteration++;
            $this->logger->info('Outbox worker running...', ['iteration' => $iteration]);

            // TODO: Implement actual outbox event processing
            // For now, this is a dummy worker that just logs status

            sleep(20);
        }

        $this->logger->notice('Outbox worker stopped');
    }

    /**
     * Async event worker - processes async events from SQS
     * Run with: php oil r worker:async
     */
    public function async()
    {
        $this->logger->notice('Async worker started');

        $iteration = 0;

        while (true) {
            if ($this->shouldStop) {
                $this->logger->notice('Async worker received shutdown signal, exiting gracefully...');
                break;
            }

            $iteration++;
            $this->logger->info('Async worker running...', ['iteration' => $iteration]);

            // TODO: Implement actual async event processing from SQS
            // For now, this is a dummy worker that just logs status

            sleep(20);
        }

        $this->logger->notice('Async worker stopped');
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
