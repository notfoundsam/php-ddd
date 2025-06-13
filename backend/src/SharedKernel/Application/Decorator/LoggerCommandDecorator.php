<?php

declare(strict_types=1);

namespace SharedKernel\Application\Decorator;

use SharedKernel\Application\Command\CommandHandlerInterface;
use SharedKernel\Application\Command\CommandInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use Throwable;

class LoggerCommandDecorator implements CommandHandlerInterface
{
    private CommandHandlerInterface $next;

    private LoggerInterface $logger;

    public function __construct(
        CommandHandlerInterface $next,
        LoggerInterface $logger
    ) {
        $this->next = $next;
        $this->logger = $logger;
    }

    /**
     * @throws Throwable
     */
    public function handle(CommandInterface $command): void
    {
        $start = microtime(true);

        try {
            $this->next->handle($command);
            $executionTime = round(microtime(true) - $start, 4);
            $this->logger->info('[PERFORMANCE] Command execution time', [
                'command' => get_class($command),
                'execution_time' => $executionTime,
            ]);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'command' => get_class($command),
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
