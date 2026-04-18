<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle\Exceptions;

use RuntimeException;
use Throwable;

class ThrottleDriverException extends RuntimeException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
