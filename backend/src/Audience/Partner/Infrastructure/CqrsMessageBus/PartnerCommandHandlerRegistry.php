<?php

declare(strict_types=1);

namespace Audience\Partner\Infrastructure\CqrsMessageBus;

use Audience\Partner\Application\Command\Auth\LogInCommand;
use Audience\Partner\Application\Command\Auth\LogInHandler;
use Audience\Partner\Application\Command\Auth\LogOutCommand;
use Audience\Partner\Application\Command\Auth\LogOutHandler;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandHandlerRegistryInterface;

final class PartnerCommandHandlerRegistry implements CommandHandlerRegistryInterface
{
    public function getCommandHandlers(): array
    {
        return [
            LogInCommand::class => LogInHandler::class,
            LogOutCommand::class => LogOutHandler::class,
        ];
    }
}
