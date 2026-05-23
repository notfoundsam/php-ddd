<?php

use Fuel\Core\Config;
use Fuel\Core\Response;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\UserResolver\SiteUserResolverInterface;

abstract class Controller_Site_Abstract extends Controller_Audience
{
    public function before()
    {
        parent::before();

        $user = Container::resolve(SiteUserResolverInterface::class)->resolve();
        if ($user !== null) {
            Container::resolve(SecurityContextInterface::class)->setCurrentUser($user);
        }
    }

    protected function redirect(string $path = ''): Response
    {
        $base = Config::get('audience.urls.site');
        if (!is_string($base) || $base === '') {
            throw new \RuntimeException('APP_URL must be set to redirect within the site audience');
        }
        return Response::redirect($base . ltrim($path, '/'));
    }
}
