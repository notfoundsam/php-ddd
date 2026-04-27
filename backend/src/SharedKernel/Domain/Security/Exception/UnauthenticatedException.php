<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\Exception;

use RuntimeException;

class UnauthenticatedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Authentication required');
    }
}
