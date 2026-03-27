<?php

declare(strict_types=1);

namespace App\Session;

use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;

class FuelPhpSessionManager extends SessionManager
{
    protected function buildSession($handler): Store
    {
        return new FuelPhpSessionStore(
            $this->config->get('session.cookie'),
            $handler,
            null,
            $this->config->get('session.serialization', 'php'),
        );
    }
}
