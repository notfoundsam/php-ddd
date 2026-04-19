<?php

namespace Partner;

use Fuel\Core\Controller;
use HttpTooManyRequestsException;
use Infrastructure\Throttle\HttpThrottleTrait;

abstract class Controller_Abstract extends Controller
{
    use HttpThrottleTrait;

    /**
     * @throws HttpTooManyRequestsException
     */
    public function before()
    {
        $this->throttleBeforeRequest();

        parent::before();
    }
}
