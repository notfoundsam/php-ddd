<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\TransactionManagerInterface;

/**
 * Records "begin" / "commit" / "rollback" onto a shared event log so tests can
 * assert ordering of transaction boundaries relative to other side effects.
 */
final class SpyTransactionManager implements TransactionManagerInterface
{
    private TestLog $log;

    public function __construct(TestLog $log)
    {
        $this->log = $log;
    }

    public function beginTransaction(): void
    {
        $this->log->append('begin');
    }

    public function commit(): void
    {
        $this->log->append('commit');
    }

    public function rollback(): void
    {
        $this->log->append('rollback');
    }
}
