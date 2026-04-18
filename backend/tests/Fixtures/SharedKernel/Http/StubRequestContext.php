<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\Http;

use SharedKernel\Application\Http\RequestContextInterface;

final class StubRequestContext implements RequestContextInterface
{
    private string $clientIp;

    public function __construct(string $clientIp = '127.0.0.1')
    {
        $this->clientIp = $clientIp;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }
}
