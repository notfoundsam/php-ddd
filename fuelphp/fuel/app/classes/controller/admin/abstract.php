<?php

use Fuel\Core\Config;
use Fuel\Core\Fuel;
use Fuel\Core\Input;
use Fuel\Core\Response;
use Infrastructure\Security\Resolver\AdminSessionResolver;
use SharedKernel\Domain\Security\SecurityContextInterface;

abstract class Controller_Admin_Abstract extends Controller_Audience
{
    private const ALLOWED_IPS = [
        '192.168.100.1',
    ];

    public function before()
    {
        // Reject before parent::before() so the buses aren't resolved for requests
        // we're about to 404. Mirrors the throttle-first order in Controller_Partner_Abstract.
        $ip = Input::real_ip();
        if (Fuel::$env != Fuel::DEVELOPMENT && !in_array($ip, self::ALLOWED_IPS, true)) {
            throw new \HttpNotFoundException();
        }

        parent::before();

        // before() only POPULATES the security context — it does NOT enforce auth.
        // Enforcement is the SecurityCommandDecorator's job (ADR-008): commands with
        // a non-null permission throw UnauthenticatedException; LogIn has null permission
        // and naturally passes through for anonymous users.
        $user = Container::resolve(AdminSessionResolver::class)->resolve();
        if ($user !== null) {
            Container::resolve(SecurityContextInterface::class)->setCurrentUser($user);
        }
    }

    protected function redirect(string $path = ''): Response
    {
        $base = Config::get('audience.urls.admin');
        if (!is_string($base) || $base === '') {
            throw new \RuntimeException('APP_URL_ADMIN must be set to redirect within the admin audience');
        }
        return Response::redirect($base . ltrim($path, '/'));
    }
}
