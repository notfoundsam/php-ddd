<?php

use Fuel\Core\Controller;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;

abstract class Controller_Audience extends Controller
{
    protected QueryBusInterface $queryBus;

    protected CommandBusInterface $commandBus;

    public function before()
    {
        $this->queryBus = Container::resolve(QueryBusInterface::class);
        $this->commandBus = Container::resolve(CommandBusInterface::class);
        parent::before();
    }
}
