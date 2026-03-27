<?php

declare(strict_types=1);

namespace App\Session;

use Illuminate\Session\Store;

class FuelPhpSessionStore extends Store
{
    public function isValidId($id): bool
    {
        // Accept both FuelPHP's 32-char and Laravel's 40-char session IDs
        return is_string($id) && ctype_alnum($id) && (strlen($id) === 32 || strlen($id) === 40);
    }
}
