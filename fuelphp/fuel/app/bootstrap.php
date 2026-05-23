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

// Bootstrap the framework - THIS LINE NEEDS TO BE FIRST!
require COREPATH . 'bootstrap.php';

// Add framework overload classes here
\Autoloader::add_classes(array(
    'Cookie' => APPPATH . 'classes/cookie.php',
    'Log' => APPPATH . 'classes/log.php',
));

// Register the autoloader
\Autoloader::register();

/**
 * Your environment.  Can be set to any of the following:
 *
 * Fuel::DEVELOPMENT
 * Fuel::TEST
 * Fuel::STAGING
 * Fuel::PRODUCTION
 */
Fuel::$env = Arr::get($_SERVER, 'FUEL_ENV', Arr::get($_ENV, 'FUEL_ENV', getenv('FUEL_ENV') ?: Fuel::DEVELOPMENT));

// Initialize the framework first so always_load.packages register their
// namespaces with the autoloader before PHP-DI compiles its container.
\Fuel::init('config.php');

// Load PHP-DI container
$container = require APPPATH . 'config/di.php';

// Make the container accessible globally
$GLOBALS['container'] = $container;
