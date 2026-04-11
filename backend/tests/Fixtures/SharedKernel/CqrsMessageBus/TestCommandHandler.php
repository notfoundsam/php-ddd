<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandHandlerInterface;

final class TestCommandHandler implements CommandHandlerInterface
{
    /** @var TestCommand[] */
    private array $handled = [];

    public function __invoke(TestCommand $command): void
    {
        $this->handled[] = $command;
    }

    /**
     * @return TestCommand[]
     */
    public function getHandled(): array
    {
        return $this->handled;
    }
}
