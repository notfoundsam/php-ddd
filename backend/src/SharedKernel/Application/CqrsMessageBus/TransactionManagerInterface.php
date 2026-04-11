<?php

declare(strict_types=1);

namespace SharedKernel\Application\CqrsMessageBus;

interface TransactionManagerInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;
}
