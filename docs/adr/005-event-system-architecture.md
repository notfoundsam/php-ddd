# ADR-005: Event System Architecture

**Status:** Accepted
**Date:** 2026-04-04

## Context

The project needs an event-driven architecture to decouple domain logic from side effects (notifications, integrations, analytics). The system must support multiple delivery patterns with different reliability guarantees and follow DDD principles where business logic is framework-agnostic.

Key requirements:
- Domain events must be stored atomically with aggregate state changes
- Non-critical side effects need best-effort async delivery
- Some notifications require delayed delivery with cancellation support
- The system must be testable without external infrastructure
- Framework-specific code must be isolated from the shared domain layer
- Commands must produce only domain events — no async or metric events from aggregates

## Decision

### Two-Tier Event Flow

The primary architectural decision: events follow a two-tier architecture rather than a single event bus.

**Tier 1 (Domain Events):** A command handler loads an aggregate, the aggregate produces domain events, and the events are stored in the outbox within the same database transaction. Commands produce only domain events through aggregates — nothing else.

**Tier 2 (Reaction Events):** A background worker picks up domain events from the outbox. Listeners process them and may produce async or scheduled events, which are dispatched by their own dedicated workers.

```
Command -> Handler -> Aggregate -> DomainEvent (outbox, same tx)
                                        |
                              Background Worker picks up
                                        |
                              Listener produces -> Async/Scheduled events
                                        |
                              Their own workers dispatch those
```

A single-tier event bus was considered but rejected because:
- Domain events require guaranteed delivery (outbox pattern, ACID with state changes)
- Async and scheduled events are secondary reactions with different reliability needs
- Each tier scales independently with its own workers
- Clear separation of what happened (domain) from what to do about it (reactions)

If non-domain events are needed elsewhere (e.g., metric events for queries), a dedicated decorator should implement that mechanism separately — this does not go through the domain event collector.

### DomainEventCollector

Initially, a generic `EventManager` was considered that would accept any event and classify it at runtime (`instanceof AsyncEventInterface`, `instanceof OutboxEventInterface`, etc.) into separate internal collections. This was rejected in favor of a narrowly-typed `DomainEventCollector` that accepts only domain events:

```php
interface DomainEventCollectorInterface
{
    public function push(OutboxEventInterface $event): void;
    public function collectFromAggregate(AggregateRoot $aggregate): void;
    public function pull(): iterable;
}
```

The type system enforces correctness at compile time — developers cannot accidentally push async events through the domain collector. The `AggregateRoot::record()` method also accepts only `OutboxEventInterface`, making the constraint end-to-end.

### Three Delivery Patterns

The system supports three delivery patterns, each with its own processor, repository, and worker:

**Outbox (Guaranteed Delivery):** Domain events stored in a database table within the same transaction as aggregate state changes. A background worker polls for unprocessed events, dispatches them to listeners, and marks them as processed. Retry with exponential backoff and Dead Letter Queue for permanent failures.

**Async (Best-Effort):** Events sent to a message queue (currently SQS) for best-effort delivery. DLQ management is delegated to the queue infrastructure (redrive policies), not application code. The queue implementation may change in the future.

**Scheduled (Delayed Delivery):** Events stored in a database table with a `scheduled_for` timestamp. A background worker polls for events whose scheduled time has passed. Supports cancellation before processing.

### Correlation and Causation IDs

Every event carries three IDs for distributed tracing across the two-tier flow:

- **id** — unique to this specific event instance (used for deduplication, idempotency, repository operations)
- **correlationId** — shared across an entire event chain (defaults to own id for root events)
- **causationId** — points to the direct parent event (null for root events)

```
OrderCreated:       id=abc  correlationId=abc  causationId=null
  SendEmail:        id=def  correlationId=abc  causationId=abc
  NotifyWarehouse:  id=ghi  correlationId=abc  causationId=abc
    WarehouseAcked: id=jkl  correlationId=abc  causationId=ghi
```

Downstream events inherit the chain via `$event->withCausation($sourceEvent)`, which copies the correlation ID and sets the causation ID automatically. No manual ID passing required.

### Interface Segregation for Repositories

Repository interfaces are split by capability, not by delivery pattern:

| Interface | Methods | Purpose |
|---|---|---|
| `EventRepositoryInterface` | store, storeBatch, getUnprocessedEvents, markProcessed, markFailed | Core processing contract for all repositories |
| `AsyncRepositoryInterface` | (marker, extends base) | Identifies queue-based repositories |
| `FailedEventRepositoryInterface` | getFailedEvents, resetForRetry, markAsResolvedManually, getEventById | DLQ management for database-backed repositories |

An `OutboxRepositoryInterface` marker was considered but not included — after extracting DLQ methods into `FailedEventRepositoryInterface`, it would be an empty interface with no additional methods. The DI container determines which repository implementation each processor receives.

`AsyncRepositoryInterface` does not extend `FailedEventRepositoryInterface`. A message queue is a pipe, not a database — event lookup via queue scan is O(n) and fundamentally fights the abstraction. DLQ management for queues should be handled by the queue infrastructure and operational tooling, not application code.

Database-backed repositories (outbox, scheduled) implement both `EventRepositoryInterface` and `FailedEventRepositoryInterface`. This allows processors to depend on the core interface while operational tooling depends on the DLQ interface.

### Event Processors and Template Method

All event processors share a common base (`AbstractEventProcessor`) that implements the processing loop:

1. Retrieve unprocessed events from repository
2. For each event, dispatch to registered listeners
3. On success: mark as processed
4. On failure: mark as failed (repository handles retry/DLQ logic)

Concrete processors (`OutboxEventProcessor`, `AsyncEventProcessor`, `ScheduledEventProcessor`) provide only the repository instance and a label for logging. The processors accept `EventRepositoryInterface` — they are not coupled to specific repository implementations.

### Listener System

Listeners implement `EventListenerInterface` with a single `handle(EventInterface $event)` method. A `ListenerProvider` maps event types to listeners with inheritance-aware resolution (listeners registered for a parent class or interface also receive child events).

Listener priorities were considered (CRITICAL/HIGH/NORMAL/LOW/BACKGROUND) but not included because:
- Domain events in the outbox worker typically have one listener per event
- Scheduled events are time-based and don't compete with each other
- Async events use standard queues that don't guarantee message ordering

Listeners execute in registration order. If ordering is needed for a specific event type in the future, it can be added without a system-wide priority framework.

### Event Registries and Factories

Event classes are registered via registries (`OutboxEventRegistry`, `AsyncEventRegistry`, `ScheduledEventRegistry`) that return lists of event class names. An `EventFactoryFactory` collects registrations from all three and builds an `EventFactory` that deserializes stored events back into domain objects.

A `ListenerProviderFactory` builds the listener provider from an `EventListenerRegistry` using the DI container to resolve listener instances with their dependencies.

### Atomic Stale Event Reset

Database-backed repositories use pessimistic locking (`SELECT ... FOR UPDATE SKIP LOCKED`) to prevent concurrent processing. Events stuck in "processing" status (from crashed workers) are reset within the same transaction as the fetch:

```
BEGIN TRANSACTION
  1. UPDATE stale 'processing' events to 'pending' (timeout-based)
  2. SELECT ... FOR UPDATE SKIP LOCKED (fetch pending events)
  3. UPDATE fetched events to 'processing'
COMMIT
```

Running the stale reset outside the transaction was considered but rejected — it creates a race window where another worker could grab a just-reset event between the reset and the lock acquisition.

### Environment-Aware Factory for Async Processing

The `SqsAsyncEventProcessorFactory` creates the appropriate async repository based on the environment:
- **Test:** `InMemoryAsyncRepository` — dispatches events synchronously, no queue infrastructure needed
- **Production/Staging/Development:** `SqsAsyncRepository` — queue-based with long polling

All configuration (queue URL, AWS region) is passed via constructor injection from the DI container. No environment variable reads inside the class — this makes the factory testable and keeps all environment-specific values in one place.

### Event Versioning and Schema Evolution

Events include schema version information with an upcasting mechanism for backward compatibility. `AbstractEvent` provides:
- `getCurrentVersion()` — declares the current schema version
- `upcastToLatestVersion(array $payload, int $fromVersion)` — migrates old payloads

Repositories compare stored version with current class version during deserialization and apply migrations sequentially using `<` comparisons (not `===`) to support multi-version jumps.

Event classes should never be deleted — events may exist in storage for hours or days. For renames or refactoring, the tombstone pattern should be used: keep the old class with an `upcastToLatestVersion()` that transforms to the new structure.

### Framework-Specific Code Placement

Database-backed repository implementations depend on a specific framework's database layer. These implementations live in each framework's own package or directory — not in the shared `backend/src/` layer. The shared layer contains only:
- Domain interfaces (`backend/src/SharedKernel/Domain/EventSystem/`)
- Framework-agnostic infrastructure (`backend/src/SharedKernel/Infrastructure/EventSystem/`) — SQS repository, in-memory repository, processors, factories, configs

This separation ensures:
- The shared domain layer has no framework dependencies
- Multiple PHP versions can coexist without IDE or static analysis conflicts
- Migration between frameworks requires only swapping repository bindings in DI config
- Shared code (serialization, hydration, poison pill handling) can be extracted into a base class within each framework's directory

### Factory vs DI Autowiring for Processors

Since both outbox and scheduled processors accept `EventRepositoryInterface`, the DI container cannot autowire them automatically — there are two implementations. Processors are wired explicitly in DI config with their specific repository. The async processor uses a factory because it needs environment-aware logic to choose between SQS and in-memory implementations.

## Components

| Component | Layer | Purpose |
|---|---|---|
| `EventInterface` | Domain | Base event contract with correlation/causation IDs |
| `AbstractEvent` | Domain | Base class with ID generation, versioning, `withCausation()` |
| `OutboxEventInterface` | Domain | Marker for domain events stored via outbox pattern |
| `AsyncEventInterface` | Domain | Marker for best-effort queue-based events |
| `ScheduledEventInterface` | Domain | Marker for delayed delivery events with `getScheduledFor()` |
| `DomainEventCollectorInterface` | Domain | Collects outbox events from aggregates |
| `EventRepositoryInterface` | Domain | Core repository contract (store, fetch, mark) |
| `AsyncRepositoryInterface` | Domain | Marker for queue-based repositories |
| `FailedEventRepositoryInterface` | Domain | DLQ operations (lookup, retry, manual resolution) |
| `EventListenerInterface` | Domain | Listener contract |
| `ListenerProviderInterface` | Domain | Maps event types to listeners |
| `EventFactoryInterface` | Domain | Deserializes stored events into domain objects |
| `AggregateRoot` | Domain | Base class that records `OutboxEventInterface` events |
| `AbstractEventProcessor` | Infrastructure | Template method for event processing loop |
| `OutboxEventProcessor` | Infrastructure | Processor for outbox events |
| `AsyncEventProcessor` | Infrastructure | Processor for async events |
| `ScheduledEventProcessor` | Infrastructure | Processor for scheduled events |
| `SqsAsyncRepository` | Infrastructure | AWS SQS queue implementation |
| `InMemoryAsyncRepository` | Infrastructure | Test implementation (synchronous dispatch) |
| `SqsAsyncEventProcessorFactory` | Infrastructure | Environment-aware SQS/in-memory selection |
| `ListenerProvider` | Infrastructure | Inheritance-aware event-to-listener mapping with caching |
| `EventFactory` | Infrastructure | Registry-based event deserialization |
| `DomainEventCollector` | Infrastructure | In-memory collection of domain events during request |

## Consequences

### Positive
- Type system prevents misuse — narrow interfaces, no runtime event classification
- Commands produce only domain events through aggregates, enforced by types
- Each delivery pattern scales independently with its own worker and storage
- Framework-specific code is fully isolated — migration swaps only repository bindings in DI
- Testable without external infrastructure (in-memory implementations)
- Correlation/causation IDs enable end-to-end tracing across event chains
- Event versioning supports schema evolution without downtime

### Trade-offs
- Two-tier flow adds complexity compared to a single event bus, but provides clear reliability boundaries
- Explicit DI wiring for processors is more verbose than autowiring, but necessary to resolve ambiguity

### Open Items
- Command handler decorator to collect events from aggregates and store in outbox atomically
- Mechanism for tier-2 listeners to produce async/scheduled events
- Transaction boundaries for listener-produced events in the worker
- Decorator for non-domain events (e.g., metrics for queries) if needed in the future
