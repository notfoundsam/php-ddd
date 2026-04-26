<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Domain\Security\AuthorizationServiceInterface;
use SharedKernel\Domain\Security\Exception\SecurityConfigurationException;
use SharedKernel\Domain\Security\Exception\UnauthenticatedException;
use SharedKernel\Domain\Security\Exception\UnauthorizedException;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;

final class SecurityCommandDecorator implements CommandBusInterface
{
    private CommandBusInterface $inner;
    private SecurityContextInterface $securityContext;
    private AuthorizationServiceInterface $authorizationService;
    private SecurityConfigInterface $securityConfig;

    public function __construct(
        CommandBusInterface $inner,
        SecurityContextInterface $securityContext,
        AuthorizationServiceInterface $authorizationService,
        SecurityConfigInterface $securityConfig
    ) {
        $this->inner = $inner;
        $this->securityContext = $securityContext;
        $this->authorizationService = $authorizationService;
        $this->securityConfig = $securityConfig;
    }

    public function dispatch(CommandInterface $command): void
    {
        $commandClass = get_class($command);
        $permissions = $this->securityConfig->getCommandPermissions();

        if (!array_key_exists($commandClass, $permissions)) {
            throw SecurityConfigurationException::commandNotRegistered($commandClass);
        }

        $permission = $permissions[$commandClass];
        if ($permission === null) {
            $this->inner->dispatch($command);
            return;
        }

        $user = $this->securityContext->getCurrentUser();
        if ($user === null) {
            throw new UnauthenticatedException();
        }

        if (!$this->authorizationService->isAllowed($user, $permission)) {
            throw new UnauthorizedException($permission);
        }

        $this->inner->dispatch($command);
    }
}
