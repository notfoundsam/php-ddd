<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\Exception;

use RuntimeException;

class UnauthorizedException extends RuntimeException
{
    private string $requiredPermission;

    public function __construct(string $requiredPermission)
    {
        parent::__construct('Access denied');
        $this->requiredPermission = $requiredPermission;
    }

    public function getRequiredPermission(): string
    {
        return $this->requiredPermission;
    }
}
