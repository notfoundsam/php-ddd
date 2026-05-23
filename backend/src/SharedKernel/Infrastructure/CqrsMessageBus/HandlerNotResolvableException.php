<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

use RuntimeException;
use Throwable;

final class HandlerNotResolvableException extends RuntimeException
{
    public static function forCommand(string $commandClass, string $handlerClass, Throwable $previous): self
    {
        return new self(
            "Handler \"$handlerClass\" registered for command \"$commandClass\""
            . ' could not be resolved by the container.',
            0,
            $previous
        );
    }

    public static function forQuery(string $queryClass, string $handlerClass, Throwable $previous): self
    {
        return new self(
            "Handler \"$handlerClass\" registered for query \"$queryClass\""
            . ' could not be resolved by the container.',
            0,
            $previous
        );
    }
}
