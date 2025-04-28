<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.8.2
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */

/**
 * -----------------------------------------------------------------------------
 *  Global database settings
 * -----------------------------------------------------------------------------
 *
 *  Set database configurations here to override environment specific
 *  configurations
 *
 */

return [
    'default' => [
        'type'        => 'mysqli',
        'connection'  => [
            'hostname'   => getenv('DB_HOSTNAME'),
            'port'       => getenv('DB_PORT'),
            'database'   => getenv('DB_DB_NAME'),
            'username'   => getenv('DB_USERNAME'),
            'password'   => getenv('DB_PASSWORD'),
        ],
    ],

    'redis' => [
        'default' => [
            'hostname' => getenv('REDIS_HOST'),
            'port' => getenv('REDIS_PORT'),
            'timeout' => null,
        ],
    ],
];
