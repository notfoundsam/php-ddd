<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

use DateTimeImmutable;

/**
 * Base class for all domain events
 *
 * All domain events MUST extend this class (not just implement EventInterface).
 * This ensures events provide required static factory methods that cannot be
 * enforced by PHP interfaces but are essential for the event system.
 *
 * Child classes must implement:
 * - fromPayload() for deserialization (abstract static method)
 * - serialize() for persistence (from EventInterface)
 *
 * Optional overrides:
 * - getCurrentVersion() to declare a schema version (default: 1)
 * - upcastToLatestVersion() to migrate old event schemas (default: no-op)
 *
 * @see EventInterface
 */
abstract class AbstractEvent implements EventInterface
{
    protected string $id;
    protected int $version;
    protected DateTimeImmutable $occurredAt;
    protected string $correlationId;
    protected ?string $causationId;

    protected function __construct(
        ?string $id = null,
        ?int $version = null,
        ?DateTimeImmutable $occurredAt = null,
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        $this->id = $id ?? $this->generateEventId();
        $this->version = $version ?? static::getCurrentVersion();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
        $this->correlationId = $correlationId ?? $this->id;
        $this->causationId = $causationId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getCausationId(): ?string
    {
        return $this->causationId;
    }

    /**
     * Creates a new event caused by the given source event
     *
     * Inherits the correlation ID from the source and sets causation ID
     * to the source event's ID. Used by listeners that produce downstream events.
     *
     * @param EventInterface $source The event that caused this one
     * @return self
     */
    public function withCausation(EventInterface $source): self
    {
        $clone = clone $this;
        $clone->correlationId = $source->getCorrelationId();
        $clone->causationId = $source->getId();

        return $clone;
    }

    /**
     * Child classes MUST implement this to support deserialization.
     *
     * @param array<string, mixed> $payload Deserialized event data with metadata
     * @return static
     */
    abstract public static function fromPayload(array $payload): self;

    /**
     * Generates a unique event ID with class-specific prefix (Stripe-style)
     * Example: orderplaced_a1b2c3d4e5f6a7b8
     */
    protected function generateEventId(): string
    {
        $className = substr(strrchr(static::class, '\\'), 1) ?: static::class;
        $prefix = strtolower(preg_replace('/Event$/', '', $className));

        // Generate cryptographically secure random ID (8 bytes = 16 hex chars)
        $randomId = bin2hex(random_bytes(8));

        return $prefix . '_' . $randomId;
    }

    /**
     * Upcasts old event versions to the latest schema
     * Override this method to handle version migrations
     *
     * Called by the repository during deserialization when a stored event version
     * is older than the current class version (getCurrentVersion()).
     *
     * MIGRATION PATTERN - Use sequential < comparisons:
     *
     * public static function upcastToLatestVersion(array $payload, int $fromVersion): array
     * {
     *     // v1 → v2: Add a new field
     *     if ($fromVersion < 2) {
     *         $payload['status'] = 'active';
     *     }
     *
     *     // v2 → v3: Rename field
     *     if ($fromVersion < 3) {
     *         $payload['new_name'] = $payload['old_name'];
     *         unset($payload['old_name']);
     *     }
     *
     *     return $payload;
     * }
     *
     * This allows events to migrate through multiple versions (v1 → v2 → v3 → ...).
     * DO NOT use === comparisons, as they break multi-version migrations.
     *
     * TOMBSTONE PATTERN - Keeping deprecated events:
     * When refactoring/renaming events, keep the old class as a "tombstone" to handle
     * events still in storage. The tombstone can redirect to the new event type.
     *
     * Example: If renaming CustomerRegisteredEvent to UserRegisteredEvent, keep the old
     * class with upcastToLatestVersion() that transforms to new structure, and mark it
     * with @deprecated annotation.
     *
     * @param array<string, mixed> $payload The raw payload to migrate
     * @param int $fromVersion The version to migrate from
     * @return array<string, mixed> The upcasted payload
     */
    public static function upcastToLatestVersion(array $payload, int $fromVersion): array
    {
        return $payload;
    }

    /**
     * Gets the current/latest version of this event class
     *
     * Called by the repository during deserialization to check if upcasting is needed.
     * Override in child classes to declare their version.
     *
     * @return int The current schema version of this event class
     */
    public static function getCurrentVersion(): int
    {
        return 1;
    }
}
