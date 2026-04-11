<?php

declare(strict_types=1);

namespace SharedKernel\Application\CqrsMessageBus\Commands;

interface CommandBusInterface
{
    public function dispatch(CommandInterface $command): void;
}
