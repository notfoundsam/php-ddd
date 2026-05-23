<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Application\Http\RequestContextInterface;
use SharedKernel\Application\Throttle\ThrottleConfigResolverInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;

final class CommandThrottleDecorator implements CommandBusInterface
{
    use ThrottleLogicTrait;

    private CommandBusInterface $inner;
    private ThrottleFactoryInterface $throttleFactory;
    private ThrottleConfigResolverInterface $throttleConfig;
    private SecurityContextInterface $securityContext;
    private RequestContextInterface $requestContext;
    private LoggerInterface $logger;

    public function __construct(
        CommandBusInterface $inner,
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

    public function dispatch(CommandInterface $command): void
    {
        $this->activateThrottle(get_class($command), 'command');
        $this->inner->dispatch($command);
    }
}
