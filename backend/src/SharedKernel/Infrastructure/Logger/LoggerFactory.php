<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Logger;

use SharedKernel\Domain\Environment;
use SharedKernel\Domain\Logger\LoggerInterface;

class LoggerFactory
{
    private Environment $environment;

    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
    }

    public function __invoke(): LoggerInterface
    {
        return new MonologLogger($this->environment);
    }
}
