<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus;

interface QueryHandlerRegistryInterface
{
    /**
     * @return array<string, string>
     */
    public function getQueryHandlers(): array;
}
