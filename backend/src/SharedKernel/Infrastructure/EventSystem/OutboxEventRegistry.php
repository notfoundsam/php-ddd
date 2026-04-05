<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

final class OutboxEventRegistry
{
    /**
     * @return array<int, class-string>
     */
    public function getOutboxEventClasses(): array
    {
        return [
        ];
    }
}
