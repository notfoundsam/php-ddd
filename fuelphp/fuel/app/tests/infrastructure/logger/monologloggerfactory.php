<?php

namespace Fuel\Core;

use Infrastructure\Logger\MonologLoggerFactory;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use ReflectionClass;
use RuntimeException;
use SharedKernel\Infrastructure\Logger\MonologLogger;

/**
 * @group App
 * @group Logger
 */
class Test_MonologLoggerFactory extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Config::load('logger', 'logger', true, true);
    }

    public function tearDown(): void
    {
        Config::load('logger', 'logger', true, true);
        parent::tearDown();
    }

    public function test_builds_null_handler_for_handler_null()
    {
        Config::set('logger.handler', 'null');

        $handler = $this->extractHandler((new MonologLoggerFactory())->__invoke());

        $this->assertInstanceOf(NullHandler::class, $handler);
    }

    public function test_builds_stdout_handler_with_json_formatter()
    {
        Config::set('logger.handler', 'stdout');
        Config::set('logger.level', 'info');

        $handler = $this->extractHandler((new MonologLoggerFactory())->__invoke());

        $this->assertInstanceOf(StreamHandler::class, $handler);
        $this->assertSame(Monolog::INFO, $handler->getLevel());
        $this->assertInstanceOf(\Monolog\Formatter\JsonFormatter::class, $handler->getFormatter());
    }

    public function test_builds_rotating_file_handler_with_configured_path_and_days()
    {
        Config::set('logger.handler', 'rotating_file');
        Config::set('logger.level', 'debug');
        Config::set('logger.path', '/tmp/test-app.log');
        Config::set('logger.rotation_days', 3);

        $handler = $this->extractHandler((new MonologLoggerFactory())->__invoke());

        $this->assertInstanceOf(RotatingFileHandler::class, $handler);
        $this->assertSame(Monolog::DEBUG, $handler->getLevel());
    }

    public function test_rotating_file_throws_when_path_is_empty()
    {
        Config::set('logger.handler', 'rotating_file');
        Config::set('logger.path', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('logger.path is required when logger.handler=rotating_file (env LOG_PATH)');

        (new MonologLoggerFactory())();
    }

    public function test_throws_on_unknown_handler()
    {
        Config::set('logger.handler', 'syslog');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown logger.handler "syslog" (expected: stdout, rotating_file, null)');

        (new MonologLoggerFactory())();
    }

    public function test_throws_on_unknown_level()
    {
        Config::set('logger.handler', 'null');
        Config::set('logger.level', 'fatal');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown logger.level "fatal"');

        (new MonologLoggerFactory())();
    }

    public function test_channel_is_suffixed_with_fuel_env()
    {
        Config::set('logger.handler', 'null');
        Config::set('logger.channel', 'svc');

        $monolog = $this->extractMonolog((new MonologLoggerFactory())->__invoke());

        $this->assertSame('svc-' . Fuel::$env, $monolog->getName());
    }

    private function extractHandler(MonologLogger $logger)
    {
        return $this->extractMonolog($logger)->getHandlers()[0];
    }

    private function extractMonolog(MonologLogger $logger): Monolog
    {
        $ref = new ReflectionClass($logger);
        $prop = $ref->getProperty('logger');
        $prop->setAccessible(true);
        return $prop->getValue($logger);
    }
}
