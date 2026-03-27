<?php

class Cookie extends Fuel\Core\Cookie
{
    protected static $config = [
        'expiration' => 0,
        'path'       => '/',
        'domain'     => null,
        'secure'     => true,
        'http_only'  => false,
        'samesite'   => 'Lax',
    ];

    public static function _init()
    {
        // Merge core config first, then apply our overrides on top
        $overrides = [
            'secure'    => true,
            'http_only' => false,
            'samesite'  => 'Lax',
        ];
        static::$config = array_merge(static::$config, \Config::get('cookie', []), $overrides);
    }

    public static function set($name, $value, $expiration = null, $path = null, $domain = null, $secure = null, $http_only = null)
    {
        if (\Fuel::$is_cli) {
            return false;
        }

        $value = \Fuel::value($value);

        is_null($expiration) and $expiration = static::$config['expiration'];
        is_null($path) and $path = static::$config['path'];
        is_null($domain) and $domain = static::$config['domain'];
        is_null($secure) and $secure = static::$config['secure'];
        is_null($http_only) and $http_only = static::$config['http_only'];

        $expiration = $expiration > 0 ? $expiration + time() : 0;

        $options = [
            'expires'  => $expiration,
            'path'     => $path,
            'secure'   => $secure,
            'httponly'  => $http_only,
            'samesite' => static::$config['samesite'],
        ];

        if ($domain) {
            $options['domain'] = $domain;
        }

        return setcookie($name, $value ?? '', $options);
    }
}
