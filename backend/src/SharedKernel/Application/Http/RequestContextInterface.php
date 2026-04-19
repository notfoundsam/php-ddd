<?php

declare(strict_types=1);

namespace SharedKernel\Application\Http;

interface RequestContextInterface
{
    public function getClientIp(): string;
}
