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
            'hostname'   => getenv('RDS_HOSTNAME') ?: getenv('DB_HOSTNAME'),
            'port'       => getenv('RDS_PORT') ?: (getenv('DB_PORT') ?: 3306),
            'database'   => getenv('RDS_DB_NAME') ?: getenv('DB_DB_NAME'),
            'username'   => getenv('RDS_USERNAME') ?: getenv('DB_USERNAME'),
            'password'   => getenv('RDS_PASSWORD') ?: getenv('DB_PASSWORD'),
        ],
    ],

    'redis' => [
        'default' => [
            'hostname' => getenv('REDIS_PRIMARY_ENDPOINT'),
            'port' => getenv('REDIS_PRIMARY_PORT') ?: 6379,
            'timeout' => null,
        ],
    ],
];
