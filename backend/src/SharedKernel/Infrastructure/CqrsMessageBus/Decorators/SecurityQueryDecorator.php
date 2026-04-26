<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;
use SharedKernel\Domain\Security\AuthorizationServiceInterface;
use SharedKernel\Domain\Security\Exception\SecurityConfigurationException;
use SharedKernel\Domain\Security\Exception\UnauthenticatedException;
use SharedKernel\Domain\Security\Exception\UnauthorizedException;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;

// Logic intentionally duplicates SecurityCommandDecorator: the two operate on different
// interfaces with different return types (void vs QueryResponseInterface), so a shared
// trait or base would obscure two small bodies for negligible reuse benefit.
final class SecurityQueryDecorator implements QueryBusInterface
{
    private QueryBusInterface $inner;
    private SecurityContextInterface $securityContext;
    private AuthorizationServiceInterface $authorizationService;
    private SecurityConfigInterface $securityConfig;

    public function __construct(
        QueryBusInterface $inner,
        SecurityContextInterface $securityContext,
        AuthorizationServiceInterface $authorizationService,
        SecurityConfigInterface $securityConfig
    ) {
        $this->inner = $inner;
        $this->securityContext = $securityContext;
        $this->authorizationService = $authorizationService;
        $this->securityConfig = $securityConfig;
    }

    public function dispatch(QueryInterface $query): QueryResponseInterface
    {
        $queryClass = get_class($query);
        $permissions = $this->securityConfig->getQueryPermissions();

        if (!array_key_exists($queryClass, $permissions)) {
            throw SecurityConfigurationException::queryNotRegistered($queryClass);
        }

        $permission = $permissions[$queryClass];
        if ($permission === null) {
            return $this->inner->dispatch($query);
        }

        $user = $this->securityContext->getCurrentUser();
        if ($user === null) {
            throw new UnauthenticatedException();
        }

        if (!$this->authorizationService->isAllowed($user, $permission)) {
            throw new UnauthorizedException($permission);
        }

        return $this->inner->dispatch($query);
    }
}
