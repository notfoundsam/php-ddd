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

// Register the FuelPHP autoloader as a fallback after Composer's. FuelPHP's
// own Autoloader::register() prepends to the SPL stack, which forces every
// composer-managed class lookup through Autoloader::lower + class_to_path
// first — measurable hot-path cost. Composer's classmap+PSR-4 covers ~99%
// of lookups; FuelPHP only needs to resolve its own classes (Fuel\Core\*,
// Cookie, Log, etc.), so it runs last.
\spl_autoload_register('Autoloader::load', true, false);

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
