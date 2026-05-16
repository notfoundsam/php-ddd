<?php

use Fuel\Core\Package;
use Infrastructure\Throttle\HttpThrottleTrait;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\UserResolverInterface;

// Partner login flow must call \Auth::instance()->login() (SimpleAuth driver) for the
// resolver to produce a non-null user. Roles come from simpleauth.groups[<id>].roles.
// Until login lands, resolve() returns null and any non-public command/query will
// correctly fail closed with UnauthenticatedException.
abstract class Controller_Partner_Abstract extends Controller_Audience
{
    use HttpThrottleTrait;

    /**
     * @throws HttpTooManyRequestsException
     */
    public function before()
    {
        $this->throttleBeforeRequest();
        Package::load('auth');

        parent::before();

        $resolver = Container::resolve(UserResolverInterface::class);
        $context = Container::resolve(SecurityContextInterface::class);
        $user = $resolver->resolve();
        if ($user !== null) {
            $context->setCurrentUser($user);
        }
    }
}
