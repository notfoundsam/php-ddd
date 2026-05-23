<?php

use Fuel\Core\Config;
use Fuel\Core\Response;
use Infrastructure\Throttle\HttpThrottleTrait;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\UserResolver\PartnerUserResolverInterface;

abstract class Controller_Partner_Abstract extends Controller_Audience
{
    use HttpThrottleTrait;

    /**
     * @throws HttpTooManyRequestsException
     */
    public function before()
    {
        $this->throttleBeforeRequest();

        parent::before();

        $user = Container::resolve(PartnerUserResolverInterface::class)->resolve();
        if ($user !== null) {
            Container::resolve(SecurityContextInterface::class)->setCurrentUser($user);
        }
    }

    protected function redirect(string $path = ''): Response
    {
        $base = Config::get('audience.urls.partner');
        if (!is_string($base) || $base === '') {
            throw new \RuntimeException('APP_URL_PARTNER must be set to redirect within the partner audience');
        }
        return Response::redirect($base . ltrim($path, '/'));
    }
}
