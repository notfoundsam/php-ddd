<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Throttle\Events;

use DateTimeImmutable;
use Exception;
use SharedKernel\Domain\EventSystem\AbstractEvent;
use SharedKernel\Domain\EventSystem\AsyncEventInterface;

final class CqrsThrottleBlockedEvent extends AbstractEvent implements AsyncEventInterface
{
    private string $messageType;
    private string $messageClass;
    private string $identifier;
    private int $retryAfter;
    private string $clientIp;
    private ?string $userType;

    private function __construct(
        string $messageType,
        string $messageClass,
        string $identifier,
        int $retryAfter,
        string $clientIp,
        ?string $userType,
        ?string $id = null,
        ?int $version = null,
        ?DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct($id, $version, $occurredAt);
        $this->messageType = $messageType;
        $this->messageClass = $messageClass;
        $this->identifier = $identifier;
        $this->retryAfter = $retryAfter;
        $this->clientIp = $clientIp;
        $this->userType = $userType;
    }

    public static function create(
        string $messageType,
        string $messageClass,
        string $identifier,
        int $retryAfter,
        string $clientIp,
        ?string $userType
    ): self {
        return new self($messageType, $messageClass, $identifier, $retryAfter, $clientIp, $userType);
    }

    /**
     * @throws Exception
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            $payload['message_type'],
            $payload['message_class'],
            $payload['identifier'],
            $payload['retry_after'],
            $payload['client_ip'] ?? '',
            $payload['user_type'] ?? null,
            $payload['id'] ?? null,
            $payload['version'] ?? null,
            isset($payload['occurred_at']) ? new DateTimeImmutable($payload['occurred_at']) : null
        );
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function getMessageClass(): string
    {
        return $this->messageClass;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    public function getUserType(): ?string
    {
        return $this->userType;
    }

    public function serialize(): array
    {
        return [
            'message_type' => $this->messageType,
            'message_class' => $this->messageClass,
            'identifier' => $this->identifier,
            'retry_after' => $this->retryAfter,
            'client_ip' => $this->clientIp,
            'user_type' => $this->userType,
        ];
    }
}
