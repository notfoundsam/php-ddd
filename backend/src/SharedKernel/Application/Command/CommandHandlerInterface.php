<?php

declare(strict_types=1);

namespace SharedKernel\Application\Command;

interface CommandHandlerInterface
{
    public function handle(CommandInterface $command): void;
}
