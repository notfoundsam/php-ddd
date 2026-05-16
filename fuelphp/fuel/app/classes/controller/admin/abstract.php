<?php

use Fuel\Core\Fuel;
use Fuel\Core\Input;

abstract class Controller_Admin_Abstract extends Controller_Audience
{
    private const ALLOWED_IPS = [
        '192.168.100.1',
    ];

    public function before()
    {
        parent::before();

        $ip = Input::real_ip();
        if (Fuel::$env != Fuel::DEVELOPMENT && !in_array($ip, self::ALLOWED_IPS, true)) {
            throw new \HttpNotFoundException();
        }
    }
}
