<?php

declare(strict_types=1);

namespace Audience\Admin\Infrastructure\CqrsMessageBus;

use Audience\Admin\Application\Command\Auth\LogInCommand;
use Audience\Admin\Application\Command\Auth\LogInHandler;
use Audience\Admin\Application\Command\Auth\LogOutCommand;
use Audience\Admin\Application\Command\Auth\LogOutHandler;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandHandlerRegistryInterface;

final class AdminCommandHandlerRegistry implements CommandHandlerRegistryInterface
{
    public function getCommandHandlers(): array
    {
        return [
            LogInCommand::class => LogInHandler::class,
            LogOutCommand::class => LogOutHandler::class,
        ];
    }
}
