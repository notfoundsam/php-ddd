<?php

namespace Admin;

use Fuel\Core\Controller;
use Fuel\Core\Fuel;
use Fuel\Core\Input;

abstract class Controller_Abstract extends Controller
{
    private const ALLOWED_IPS = [
        '192.168.100.1',
    ];

    public function before()
    {
        $ip = Input::real_ip();

        if (Fuel::$env != Fuel::DEVELOPMENT && !in_array($ip, self::ALLOWED_IPS)) {
            throw new \HttpNotFoundException();
        }

        parent::before();
    }
}
