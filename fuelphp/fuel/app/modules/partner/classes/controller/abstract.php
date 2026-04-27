<?php

namespace Partner;

use Container;
use Fuel\Core\Controller;
use Fuel\Core\Package;
use HttpTooManyRequestsException;
use Infrastructure\Throttle\HttpThrottleTrait;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\UserResolverInterface;

// Partner login flow must call \Auth::instance()->login() (SimpleAuth driver) for the
// resolver to produce a non-null user. Roles come from simpleauth.groups[<id>].roles.
// Until login lands, resolve() returns null and any non-public command/query will
// correctly fail closed with UnauthenticatedException.
abstract class Controller_Abstract extends Controller
{
    use HttpThrottleTrait;

    /**
     * @throws HttpTooManyRequestsException
     */
    public function before()
    {
        $this->throttleBeforeRequest();
        Package::load('auth');

        $resolver = Container::resolve(UserResolverInterface::class);
        $context = Container::resolve(SecurityContextInterface::class);
        $user = $resolver->resolve();
        if ($user !== null) {
            $context->setCurrentUser($user);
        }

        parent::before();
    }
}
