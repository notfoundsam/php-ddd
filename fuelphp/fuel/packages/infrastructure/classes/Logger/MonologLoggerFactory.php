<?php

declare(strict_types=1);

namespace Infrastructure\Logger;

use Fuel\Core\Config;
use Fuel\Core\Fuel;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use RuntimeException;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\Logger\MonologLogger as DomainMonologLogger;

class MonologLoggerFactory
{
    private const LEVELS = [
        'debug'     => MonologLogger::DEBUG,
        'info'      => MonologLogger::INFO,
        'notice'    => MonologLogger::NOTICE,
        'warning'   => MonologLogger::WARNING,
        'error'     => MonologLogger::ERROR,
        'critical'  => MonologLogger::CRITICAL,
        'alert'     => MonologLogger::ALERT,
        'emergency' => MonologLogger::EMERGENCY,
    ];

    public function __invoke(): LoggerInterface
    {
        Config::load('logger', true);

        $channel = (string) Config::get('logger.channel', 'php-ddd');
        $level = self::resolveLevel((string) Config::get('logger.level', 'info'));
        $handlerName = (string) Config::get('logger.handler', 'stdout');

        $logger = new MonologLogger($channel . '-' . Fuel::$env);
        $logger->pushHandler(self::buildHandler($handlerName, $level));

        return new DomainMonologLogger($logger);
    }

    private static function buildHandler(string $name, int $level): HandlerInterface
    {
        if ($name === 'rotating_file') {
            $path = Config::get('logger.path');
            if (empty($path)) {
                throw new RuntimeException('logger.path is required when logger.handler=rotating_file (env LOG_PATH)');
            }
            $days = (int) Config::get('logger.rotation_days', 7);
            return new RotatingFileHandler((string) $path, $days, $level);
        }

        if ($name === 'stdout') {
            $handler = new StreamHandler('php://stdout', $level);
            $handler->setFormatter(new JsonFormatter());
            return $handler;
        }

        if ($name === 'null') {
            return new NullHandler();
        }

        throw new RuntimeException(sprintf(
            'Unknown logger.handler "%s" (expected: stdout, rotating_file, null)',
            $name
        ));
    }

    private static function resolveLevel(string $level): int
    {
        $key = strtolower($level);
        if (!isset(self::LEVELS[$key])) {
            throw new RuntimeException(sprintf(
                'Unknown logger.level "%s" (expected one of: %s)',
                $level,
                implode(', ', array_keys(self::LEVELS))
            ));
        }
        return self::LEVELS[$key];
    }
}
