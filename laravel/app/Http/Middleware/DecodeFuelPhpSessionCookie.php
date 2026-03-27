<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DecodeFuelPhpSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('session.cookie');
        $cookieValue = $request->cookies->get($cookieName);

        if ($cookieValue !== null) {
            $decoded = @unserialize($cookieValue);

            if (is_array($decoded) && isset($decoded[0]) && is_string($decoded[0])) {
                // Replace the serialized cookie with the raw session ID
                $request->cookies->set($cookieName, $decoded[0]);
            }
        }

        $response = $next($request);

        // Re-encode the session ID back to FuelPHP's serialized format for the cookie
        $sessionId = $request->session()->getId();
        if ($sessionId) {
            $encoded = serialize([$sessionId]);
            $response->headers->setCookie(
                cookie(
                    $cookieName,
                    $encoded,
                    (int) config('session.lifetime'),
                    config('session.path'),
                    config('session.domain'),
                    config('session.secure'),
                    config('session.http_only'),
                    false,
                    config('session.same_site')
                )
            );
        }

        return $response;
    }
}
