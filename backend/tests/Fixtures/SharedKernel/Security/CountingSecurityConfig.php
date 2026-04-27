<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Security;

use SharedKernel\Domain\Security\SecurityConfigInterface;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestCommand;
use Tests\Fixtures\SharedKernel\CqrsMessageBus\TestQuery;

final class CountingSecurityConfig implements SecurityConfigInterface
{
    public int $commandCalls = 0;
    public int $queryCalls = 0;
    public int $roleCalls = 0;

    public function getCommandPermissions(): array
    {
        $this->commandCalls++;
        return [TestCommand::class => 'do'];
    }

    public function getQueryPermissions(): array
    {
        $this->queryCalls++;
        return [TestQuery::class => 'read'];
    }

    public function getRolePermissions(): array
    {
        $this->roleCalls++;
        return ['viewer' => ['read']];
    }
}
