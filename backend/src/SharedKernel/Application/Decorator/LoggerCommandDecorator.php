<?php

declare(strict_types=1);

namespace SharedKernel\Application\Decorator;

use SharedKernel\Application\Command\CommandHandlerInterface;
use SharedKernel\Application\Command\CommandInterface;

class LoggerCommandDecorator implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): void
    {
        // TODO: Implement handle() method.
    }
}
