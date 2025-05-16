<?php

declare(strict_types=1);

namespace SharedKernel\Application\Transaction;

interface TransactionManagerInterface
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;
}
