<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;
use SharedKernel\Application\Http\RequestContextInterface;
use SharedKernel\Application\Throttle\ThrottleConfigResolverInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;

final class QueryThrottleDecorator implements QueryBusInterface
{
    use ThrottleLogicTrait;

    private QueryBusInterface $inner;
    private ThrottleFactoryInterface $throttleFactory;
    private ThrottleConfigResolverInterface $throttleConfig;
    private SecurityContextInterface $securityContext;
    private RequestContextInterface $requestContext;
    private LoggerInterface $logger;

    public function __construct(
        QueryBusInterface $inner,
        ThrottleFactoryInterface $throttleFactory,
        ThrottleConfigResolverInterface $throttleConfig,
        SecurityContextInterface $securityContext,
        RequestContextInterface $requestContext,
        LoggerInterface $logger
    ) {
        $this->inner = $inner;
        $this->throttleFactory = $throttleFactory;
        $this->throttleConfig = $throttleConfig;
        $this->securityContext = $securityContext;
        $this->requestContext = $requestContext;
        $this->logger = $logger;
    }

    public function dispatch(QueryInterface $query): QueryResponseInterface
    {
        $this->activateThrottle(get_class($query), 'query');

        return $this->inner->dispatch($query);
    }
}
