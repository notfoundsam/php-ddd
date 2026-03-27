<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FuelPhpAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get('id');

        if ($userId !== null) {
            $user = User::find((int) $userId);

            if ($user !== null) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
