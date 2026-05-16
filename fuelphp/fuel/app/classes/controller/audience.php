<?php

use Fuel\Core\Controller;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;

/**
 * Base class for every audience controller (Site / Admin / Partner).
 *
 * Resolves the query bus once in `before()` so leaf controllers can dispatch through
 * `$this->bus`. Audience-specific abstracts (`Controller_Site_Abstract`,
 * `Controller_Admin_Abstract`, `Controller_Partner_Abstract`) extend this and layer their
 * own cross-cutting on top — IP restriction for admin, auth resolution for partner, etc.
 */
abstract class Controller_Audience extends Controller
{
    protected QueryBusInterface $bus;

    public function before()
    {
        $this->bus = Container::resolve(QueryBusInterface::class);
        parent::before();
    }
}
