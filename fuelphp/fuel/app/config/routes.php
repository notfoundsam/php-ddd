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

use Fuel\Core\Response;

return array(
	/**
	 * -------------------------------------------------------------------------
	 *  Default route
	 * -------------------------------------------------------------------------
	 *
	 */

	'_root_' => 'site/home/index',
    'products' => 'site/home/products',
    'login' => 'site/auth/login',
    'logout' => 'site/auth/logout',
    'admin' => 'admin/welcome/index',
    'admin/login' => 'admin/auth/login',
    'admin/logout' => 'admin/auth/logout',
    'partner' => 'partner/welcome/index',
    'partner/login' => 'partner/auth/login',
    'partner/logout' => 'partner/auth/logout',
    'healthcheck' => function () { return Response::forge('healthy'); },

	/**
	 * -------------------------------------------------------------------------
	 *  Page not found
	 * -------------------------------------------------------------------------
	 *
	 */

	'_404_' => 'welcome/404',
	'_429_' => function () {
		return Response::forge('Too many requests. Please try again later.', 429);
	},

	/**
	 * -------------------------------------------------------------------------
	 *  Example route with optional parameter
	 * -------------------------------------------------------------------------
	 *
	 */

	'hello(/:name)?' => array('welcome/hello', 'name' => 'hello'),
);
