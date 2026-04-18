<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use SharedKernel\Domain\Throttle\Events\CqrsThrottleBlockedEvent;
use SharedKernel\Domain\Throttle\Events\CqrsThrottleWarningEvent;
use SharedKernel\Domain\Throttle\Exceptions\ThrottleDriverException;
use SharedKernel\Domain\Throttle\Exceptions\ThrottleException;
use Throwable;

trait ThrottleLogicTrait
{
    private function activateThrottle(string $messageClass, string $messageType): void
    {
        $throttleException = null;

        try {
            $isAuthenticated = $this->securityContext->hasAuthenticatedUser();

            if ($isAuthenticated) {
                $user = $this->securityContext->getCurrentUser();
                $userType = $user->getType();
                $resolved = $this->throttleConfig->resolve($messageClass, $messageType, $userType);

                if ($resolved === null) {
                    return;
                }

                $identifier = $userType . ':' . $user->getId();
            } else {
                $ip = $this->requestContext->getClientIp();
                $resolved = $this->throttleConfig->resolve($messageClass, $messageType, null);

                if ($resolved === null) {
                    return;
                }

                $identifier = 'ip:' . $ip;
                $userType = null;
            }

            $config = $resolved->getConfig();
            $scope = $resolved->getScope();
            $clientIp = $this->requestContext->getClientIp();
            $throttler = $this->throttleFactory->create($config, $scope);
            $result = $throttler->attempt($identifier);

            $shortName = substr(strrchr($messageClass, '\\'), 1) ?: $messageClass;

            if ($result->isWarningTriggered() && $result->getCurrentAttempts() === $config->getWarningLimit()) {
                $this->fireWarningEvent(
                    $messageType,
                    $shortName,
                    $identifier,
                    $config->getWarningLimit(),
                    $clientIp,
                    $userType
                );
            }

            if (!$result->isAllowed()) {
                $throttleException = ThrottleException::blockedIdentifier(
                    $result->getRetryAfter(),
                    $identifier
                );

                if ($result->getCurrentAttempts() === $config->getBlockLimit()) {
                    $this->fireBlockedEvent(
                        $messageType,
                        $shortName,
                        $identifier,
                        $result->getRetryAfter(),
                        $clientIp,
                        $userType
                    );
                }
            }
        } catch (ThrottleDriverException $e) {
            $this->logger->error("[THROTTLE] Driver connection failed - allowing $messageType");
        }

        if ($throttleException !== null) {
            throw $throttleException;
        }
    }

    private function fireWarningEvent(
        string $messageType,
        string $messageName,
        string $identifier,
        int $requestCount,
        string $clientIp,
        ?string $userType
    ): void {
        $this->logger->warning('[THROTTLE] Warning threshold reached', [
            'message_type' => $messageType,
            'message_class' => $messageName,
            'identifier' => $identifier,
            'request_count' => $requestCount,
            'throttle_action' => 'warning_triggered',
        ]);

        try {
            $event = CqrsThrottleWarningEvent::create(
                $messageType,
                $messageName,
                $identifier,
                $requestCount,
                $clientIp,
                $userType
            );
            $this->asyncEventProcessor->store($event);
        } catch (Throwable $e) {
            $this->logger->error('[THROTTLE] Failed to process warning event', [
                'error' => $e->getMessage(),
                'message_type' => $messageType,
                'identifier' => $identifier,
            ]);
        }
    }

    private function fireBlockedEvent(
        string $messageType,
        string $messageName,
        string $identifier,
        int $retryAfter,
        string $clientIp,
        ?string $userType
    ): void {
        $this->logger->warning('[THROTTLE] Request blocked', [
            'message_type' => $messageType,
            'message_class' => $messageName,
            'identifier' => $identifier,
            'retry_after' => $retryAfter,
            'throttle_action' => 'request_blocked',
        ]);

        try {
            $event = CqrsThrottleBlockedEvent::create(
                $messageType,
                $messageName,
                $identifier,
                $retryAfter,
                $clientIp,
                $userType
            );
            $this->asyncEventProcessor->store($event);
        } catch (Throwable $e) {
            $this->logger->error('[THROTTLE] Failed to process blocked event', [
                'error' => $e->getMessage(),
                'message_type' => $messageType,
                'identifier' => $identifier,
            ]);
        }
    }
}
