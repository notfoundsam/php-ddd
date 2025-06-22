<?php

use Fuel\Core\Config;
use Monolog\Logger;

class Log extends Fuel\Core\Log
{
    public static function _init()
    {
    }

    public static function debug($msg, $context = null): bool
    {
        return static::write(Logger::DEBUG, $msg, $context);
    }

    public static function info($msg, $context = null): bool
    {
        return static::write(Logger::INFO, $msg, $context);
    }

    public static function notice($msg, $context = null): bool
    {
        return static::write(Logger::NOTICE, $msg, $context);
    }

    public static function warning($msg, $context = null): bool
    {
        return static::write(Logger::WARNING, $msg, $context);
    }

    public static function error($msg, $context = null): bool
    {
        return static::write(Logger::ERROR, $msg, $context);
    }

    public static function critical($msg, $context = null): bool
    {
        return static::write(Logger::CRITICAL, $msg, $context);
    }

    public static function alert($msg, $context = null): bool
    {
        return static::write(Logger::ALERT, $msg, $context);
    }

    public static function emergency($msg, $context = null): bool
    {
        return static::write(Logger::EMERGENCY, $msg, $context);
    }

    public static function write($level, $msg, $context = null)
    {
        is_null($context) && $context = [];

        if (is_string($context)) {
            $msg = $context . ' - ' . $msg;
            $context = [];
        }

        if (Config::get('profiling')) {
            empty($context) ? Console::log($msg) : Console::log(json_encode($context) . ' - ' . $msg);
        }

        $logger = Container::logger();

        switch ($level) {
            case Logger::DEBUG:
                $logger->debug($msg, $context);
                break;
            case Logger::INFO:
                $logger->info($msg, $context);
                break;
            case Logger::NOTICE:
                $logger->notice($msg, $context);
                break;
            case Logger::WARNING:
                $logger->warning($msg, $context);
                break;
            case Logger::ERROR:
                $logger->error($msg, $context);
                break;
            case Logger::CRITICAL:
                $logger->critical($msg, $context);
                break;
            case Logger::ALERT:
                $logger->alert($msg, $context);
                break;
            case Logger::EMERGENCY:
                $logger->emergency($msg, $context);
                break;
        }

        return true;
    }
}
