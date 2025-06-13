<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Repository;

use SharedKernel\Domain\Event\OutboxEventInterface;

interface OutboxRepositoryInterface
{
    public function save(OutboxEventInterface ...$events): void;
}
