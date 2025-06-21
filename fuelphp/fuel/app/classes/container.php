<?php

class Container
{
    public static function resolve($class)
    {
        return $GLOBALS['container']->get($class);
    }
}
