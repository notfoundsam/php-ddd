<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;

final class SecondTestCommand implements CommandInterface
{
}
