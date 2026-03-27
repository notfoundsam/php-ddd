<?php

declare(strict_types=1);

namespace App\Providers;

use App\Session\FuelPhpSessionHandler;
use App\Session\FuelPhpSessionManager;
use Illuminate\Support\ServiceProvider;

class FuelPhpSessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('session', function ($app) {
            return new FuelPhpSessionManager($app);
        });
    }

    public function boot(): void
    {
        $this->app['session']->extend('fuelphp_redis', function ($app) {
            $expiration = (int) config('session.lifetime') * 60;

            return new FuelPhpSessionHandler($expiration);
        });
    }
}
