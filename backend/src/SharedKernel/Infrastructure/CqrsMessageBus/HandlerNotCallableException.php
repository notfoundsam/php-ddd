<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

use LogicException;

final class HandlerNotCallableException extends LogicException
{
    public static function forCommand(string $commandClass, string $handlerClass): self
    {
        return new self(
            "Handler \"$handlerClass\" registered for command \"$commandClass\" is not callable. "
            . 'Handler classes must implement __invoke().'
        );
    }

    public static function forQuery(string $queryClass, string $handlerClass): self
    {
        return new self(
            "Handler \"$handlerClass\" registered for query \"$queryClass\" is not callable. "
            . 'Handler classes must implement __invoke().'
        );
    }
}
