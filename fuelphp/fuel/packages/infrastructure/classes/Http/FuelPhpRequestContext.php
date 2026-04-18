<?php

declare(strict_types=1);

namespace Infrastructure\Http;

use Fuel\Core\Input;
use SharedKernel\Application\Http\RequestContextInterface;

final class FuelPhpRequestContext implements RequestContextInterface
{
    public function getClientIp(): string
    {
        return Input::real_ip();
    }
}
