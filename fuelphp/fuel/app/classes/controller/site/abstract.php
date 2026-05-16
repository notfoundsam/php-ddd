<?php

/**
 * Public-site audience controllers. Inherits query-bus access from `Controller_Audience`
 * and adds no cross-cutting of its own — public routes are anonymous, throttle/security
 * live inside the query bus decorator chain.
 */
abstract class Controller_Site_Abstract extends Controller_Audience
{
}
