<?php

declare(strict_types=1);

namespace Infrastructure\CqrsMessageBus;

use Fuel\Core\DB;
use SharedKernel\Application\CqrsMessageBus\TransactionManagerInterface;

final class FuelPhpTransactionManager implements TransactionManagerInterface
{
    public function beginTransaction(): void
    {
        DB::start_transaction();
    }

    public function commit(): void
    {
        DB::commit_transaction();
    }

    public function rollback(): void
    {
        DB::rollback_transaction();
    }
}
