<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Logger\LoggerInterface;

class MonologLogger implements LoggerInterface
{
    private const LOG_FILE_PATH = '/app/fuelphp/fuel/app/logs/app.log';

    private Logger $logger;

    public function __construct(Environment $environment)
    {
        $this->logger = new Logger('php-ddd-' . $environment->getValue());

        if ($environment->isDevelopment()) {
            $streamHandler = new RotatingFileHandler(self::LOG_FILE_PATH, 7, Logger::DEBUG);
        } else {
            $streamHandler = new StreamHandler('php://stdout', Logger::INFO);
            $streamHandler->setFormatter(new JsonFormatter());
        }

        $this->logger->pushHandler($streamHandler);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
}
