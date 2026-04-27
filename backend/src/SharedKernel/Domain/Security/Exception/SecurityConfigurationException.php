<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security\Exception;

use LogicException;

class SecurityConfigurationException extends LogicException
{
    public static function commandNotRegistered(string $commandClass): self
    {
        return new self("No security configuration found for command: $commandClass");
    }

    public static function queryNotRegistered(string $queryClass): self
    {
        return new self("No security configuration found for query: $queryClass");
    }

    public static function duplicateCommandEntry(string $commandClass): self
    {
        return new self("Duplicate security configuration entry for command: $commandClass");
    }

    public static function duplicateQueryEntry(string $queryClass): self
    {
        return new self("Duplicate security configuration entry for query: $queryClass");
    }
}
