<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

interface CommandHandlerRegistryInterface
{
    /**
     * @return array<string, string>
     */
    public function getCommandHandlers(): array;
}
