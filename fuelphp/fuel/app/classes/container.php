<?php

use SharedKernel\Domain\Logger\LoggerInterface;

class Container
{
    public static function resolve($class)
    {
        return $GLOBALS['container']->get($class);
    }

    public static function logger(): LoggerInterface
    {
        return self::resolve(LoggerInterface::class);
    }
}
