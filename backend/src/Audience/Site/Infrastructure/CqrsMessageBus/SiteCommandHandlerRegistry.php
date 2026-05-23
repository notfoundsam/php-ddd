<?php

declare(strict_types=1);

namespace Audience\Site\Infrastructure\CqrsMessageBus;

use Audience\Site\Application\Command\Auth\LogInCommand;
use Audience\Site\Application\Command\Auth\LogInHandler;
use Audience\Site\Application\Command\Auth\LogOutCommand;
use Audience\Site\Application\Command\Auth\LogOutHandler;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandHandlerRegistryInterface;

final class SiteCommandHandlerRegistry implements CommandHandlerRegistryInterface
{
    public function getCommandHandlers(): array
    {
        return [
            LogInCommand::class => LogInHandler::class,
            LogOutCommand::class => LogOutHandler::class,
        ];
    }
}
