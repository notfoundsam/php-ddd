<?php

namespace Infrastructure\Throttle;

use Container;
use HttpTooManyRequestsException;
use SharedKernel\Application\Http\RequestContextInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Throttle\Exceptions\ThrottleDriverException;
use SharedKernel\Domain\Throttle\ThrottleConfig;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;

trait HttpThrottleTrait
{
    protected function throttleBeforeRequest(): void
    {
        /** @var SecurityContextInterface $securityContext */
        $securityContext = Container::resolve(SecurityContextInterface::class);

        if ($securityContext->hasAuthenticatedUser()) {
            return;
        }

        try {
            /** @var RequestContextInterface $requestContext */
            $requestContext = Container::resolve(RequestContextInterface::class);
            $clientIp = $requestContext->getClientIp();

            $config = ThrottleConfig::fromArray([
                'warning_limit' => 100,
                'block_limit' => 200,
                'window' => 60,
                'penalties' => [300, 3600, 10800],
                'recovery_period' => 10800,
            ]);

            /** @var ThrottleFactoryInterface $throttleFactory */
            $throttleFactory = Container::resolve(ThrottleFactoryInterface::class);
            $throttler = $throttleFactory->create($config, '__default__');

            $identifier = 'ip:' . $clientIp;
            $result = $throttler->attempt($identifier);

            /** @var LoggerInterface $logger */
            $logger = Container::resolve(LoggerInterface::class);

            if ($result->isWarningTriggered() && $result->getCurrentAttempts() === $config->getWarningLimit()) {
                $logger->warning('[HTTP_THROTTLE] Warning threshold reached', [
                    'identifier' => $identifier,
                    'request_count' => $result->getCurrentAttempts(),
                ]);
            }

            if (!$result->isAllowed()) {
                if ($result->getCurrentAttempts() === $config->getBlockLimit()) {
                    $logger->warning('[HTTP_THROTTLE] Request blocked', [
                        'identifier' => $identifier,
                        'retry_after' => $result->getRetryAfter(),
                    ]);
                }
                throw new HttpTooManyRequestsException($result->getRetryAfter());
            }
        } catch (ThrottleDriverException $e) {
            /** @var LoggerInterface $logger */
            $logger = Container::resolve(LoggerInterface::class);
            $logger->error('[HTTP_THROTTLE] Driver connection failed - allowing request');
        }
    }
}
