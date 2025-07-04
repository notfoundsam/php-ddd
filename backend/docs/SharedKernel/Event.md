# Event System Design Document

## Overview

The Event system in this PHP DDD codebase implements a sophisticated event-driven architecture that supports multiple processing patterns including synchronous, asynchronous, and outbox patterns. The system is designed around Domain-Driven Design principles with clear separation between domain contracts and infrastructure implementations.

## Architecture

### Core Design Patterns

- **Interface Segregation**: Multiple focused interfaces rather than monolithic contracts
- **Strategy Pattern**: Pluggable event bus implementations (InMemory, AWS SQS)
- **Observer Pattern**: Events published to registered listeners
- **Command Pattern**: Events represent domain facts that have occurred
- **Mediator Pattern**: EventManager acts as central coordinator

### Architectural Layers

```
┌─────────────────────────────────────────────────────────────┐
│                     Domain Layer                            │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐  │
│  │  EventInterface │  │EventListenerInt.│  │EventBusInt. │  │
│  │                 │  │                 │  │             │  │
│  └─────────────────┘  └─────────────────┘  └─────────────┘  │
│           │                     │                   │       │
└───────────┼─────────────────────┼───────────────────┼───────┘
            │                     │                   │
┌───────────┼─────────────────────┼───────────────────┼───────┐
│           │        Infrastructure Layer             │       │
│  ┌────────▼────────┐  ┌─────────▼───────┐  ┌────────▼────┐  │
│  │   EventManager  │  │ ListenerProvider│  │InMemoryBus  │  │
│  │                 │  │                 │  │AwsSqsEventBus│  │
│  └─────────────────┘  └─────────────────┘  └─────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Event Classification System

The system implements a three-tier event classification based on processing requirements:

### 1. TransactionalEventInterface
- **Purpose**: Events processed within the same database transaction
- **Processing**: Synchronous, immediate
- **Use Cases**: Domain invariant enforcement, immediate consistency
- **Location**: `backend/src/SharedKernel/Domain/Event/TransactionalEventInterface.php`

### 2. PostCommitEventInterface
- **Purpose**: Events processed after successful transaction commit
- **Processing**: Asynchronous, eventual consistency
- **Features**: Requires `serialize()` method for message transport
- **Use Cases**: External notifications, email sending, audit logging
- **Location**: `backend/src/SharedKernel/Domain/Event/PostCommitEventInterface.php`

### 3. OutboxEventInterface
- **Purpose**: Events stored for reliable message delivery patterns
- **Processing**: Outbox pattern implementation
- **Features**: Requires `serialize()` method for persistence
- **Use Cases**: Distributed systems integration, guaranteed delivery
- **Location**: `backend/src/SharedKernel/Domain/Event/OutboxEventInterface.php`

## Key Components

### EventManager

**File**: `backend/src/SharedKernel/Infrastructure/Event/EventManager.php`

**Responsibilities**:
- Central event collection and classification
- Event segregation into appropriate processing queues
- Integration with DDD aggregate roots
- Pull-based event retrieval for different processing patterns

**Key Methods**:
```php
public function push(EventInterface $event): void
public function collectFromAggregate(AggregateRoot $aggregate): void
public function pullSyncEvents(): array
public function pullAsyncEvents(): array
public function pullOutboxEvents(): array
```

**Event Flow**:
1. Events are pushed or collected from aggregates
2. Automatically classified based on interface implementation
3. Stored in separate internal queues by type
4. Retrieved via pull methods for processing
5. Cleared after retrieval (one-time consumption)

### EventDispatcher

**File**: `backend/src/SharedKernel/Infrastructure/Event/InMemoryEventDispatcher.php`

**Purpose**: Synchronous processing of TransactionalEventInterface events

**Key Features**:
- Immediate listener invocation within same request cycle
- Integration with ListenerProvider for event-to-listener mapping
- Ensures transactional consistency

### EventBus Implementations

#### InMemoryEventBus
**File**: `backend/src/SharedKernel/Infrastructure/Event/InMemoryEventBus.php`

**Purpose**: In-process asynchronous event processing
**Best For**: Development, testing, simple applications

#### AwsSqsEventBus
**File**: `backend/src/SharedKernel/Infrastructure/Event/AwsSqsEventBus.php`

**Purpose**: External message queue integration for production systems

**Key Features**:
- Message deduplication using event ID
- Message grouping by event class
- JSON serialization of event data
- Error handling with logging and re-throwing
- Message attributes for type identification

**Configuration**:
```php
$sqsEventBus = new AwsSqsEventBus($sqsClient, $queueUrl);
```

### ListenerProvider

**File**: `backend/src/SharedKernel/Infrastructure/Event/ListenerProvider.php`

**Purpose**: Event-to-listener mapping registry with inheritance support

**Key Features**:
- Registration by event class name
- Inheritance-aware listener resolution
- Supports multiple listeners per event
- Preserves listener registration order

**Enhanced Inheritance Support**:
The system now supports listening to parent classes and interfaces:
```php
public function getListenersForEvent(EventInterface $event): iterable
{
    $listeners = [];
    $eventClasses = array_merge(
        class_parents($event) ?: [],
        class_implements($event) ?: [],
        [get_class($event)]
    );

    foreach ($eventClasses as $eventClass) {
        if (isset($this->listeners[$eventClass])) {
            $listeners = array_merge($listeners, $this->listeners[$eventClass]);
        }
    }

    return $listeners;
}
```

## Event Flow

### 1. Event Recording (Domain Layer)
```php
// In AggregateRoot
protected function record(EventInterface $event): void
{
    $this->recordedEvents[] = $event;
}
```

### 2. Event Collection (Application Layer)
```php
// EventManager collects from aggregates
$eventManager->collectFromAggregate($aggregate);
```

### 3. Event Classification and Processing
```php
// EventManager automatically routes events
if ($event instanceof TransactionalEventInterface) {
    // → EventDispatcher (synchronous)
} elseif ($event instanceof PostCommitEventInterface) {
    // → EventBus (asynchronous)
} elseif ($event instanceof OutboxEventInterface) {
    // → Outbox storage
}
```

## Integration with DDD Patterns

### Aggregate Root Integration

**File**: `backend/src/SharedKernel/Domain/Aggregate/AggregateRoot.php`

**Features**:
- Event recording within domain entities
- Event release mechanism for external collection
- Automatic event clearing after release
- Encapsulation of domain event logic

**Usage Pattern**:
```php
class UserAggregate extends AggregateRoot
{
    public function activate(): void
    {
        // Domain logic
        $this->status = 'active';
        
        // Record domain event
        $this->record(new UserActivatedEvent($this->id));
    }
}
```

### Domain Event Characteristics

All events implement `EventInterface` with:
- **Unique ID**: For deduplication and tracking
- **Version**: For event evolution and compatibility
- **Timestamp**: For temporal ordering and audit trails

## Error Handling and Reliability

### EventManager Error Handling
- **Strict Classification**: Throws `LogicException` for unclassified events
- **Type Safety**: Strong typing prevents runtime errors
- **Fail-Fast**: Immediate error detection during event pushing

### AWS SQS Error Handling
```php
try {
    $this->sqs->sendMessage([...]);
} catch (SqsException $e) {
    error_log(sprintf('Failed to publish event %s: %s', get_class($event), $e->getMessage()));
    throw $e;
}
```

### Reliability Features
- **Message Deduplication**: Uses event ID for SQS deduplication
- **Message Grouping**: Groups messages by event class for ordered processing
- **Serialization**: Consistent serialization interface for reliable transport
- **Pull-Based Processing**: Prevents event loss through explicit pull mechanisms

## Configuration and Setup

### Dependency Injection Setup
```php
// Example DI configuration
$container->set(EventManagerInterface::class, function() {
    return new EventManager();
});

$container->set(EventBusInterface::class, function() {
    return new AwsSqsEventBus($sqsClient, $queueUrl);
});

$container->set(ListenerProviderInterface::class, function() {
    $provider = new ListenerProvider();
    $provider->addListener(UserCreatedEvent::class, $userCreatedListener);
    return $provider;
});
```

### Event Listener Registration
```php
// Register listeners for specific events
$listenerProvider->addListener(UserCreatedEvent::class, $emailNotificationListener);
$listenerProvider->addListener(UserCreatedEvent::class, $auditLogListener);

// Listeners can also be registered for parent classes/interfaces
$listenerProvider->addListener(DomainEventInterface::class, $genericAuditListener);
```

## Testing Strategy

### Test Structure
- **Unit Tests**: Focus on individual component behavior
- **Integration Tests**: Test component interactions
- **Fixtures**: Shared test data and mock implementations

### Key Test Files
- `EventManagerTest.php`: Tests event classification and collection
- `ListenerProviderTest.php`: Tests listener registration and discovery
- `InMemoryEventDispatcherTest.php`: Tests synchronous event processing

### Testing Patterns
- **Mocking**: Extensive use of PHPUnit mocks for isolation
- **Behavioral Testing**: Focus on behavior rather than implementation
- **Edge Case Coverage**: Tests empty states, multiple events, error conditions

## Best Practices

### Event Design
1. **Immutability**: Events should be immutable once created
2. **Serialization**: PostCommit and Outbox events must be serializable
3. **Versioning**: Include version information for event evolution
4. **Naming**: Use past tense (e.g., `UserCreatedEvent`, not `CreateUserEvent`)

### Error Handling
1. **Fail Fast**: Validate events early in the process
2. **Logging**: Log errors before re-throwing
3. **Idempotency**: Ensure event processing is idempotent
4. **Dead Letter Queues**: Plan for failed message handling

### Performance Considerations
1. **Batch Processing**: Process multiple events together when possible
2. **Async Processing**: Use PostCommit events for non-critical operations
3. **Event Size**: Keep events small and focused
4. **Listener Efficiency**: Ensure listeners are performant

## Future Enhancements

### Planned Improvements
1. **Configuration Management**: More explicit event system configuration
2. **Retry Logic**: Enhanced retry mechanisms for failed events
3. **Monitoring**: Metrics and health checks for event processing
4. **Dead Letter Queues**: More sophisticated failed message handling
5. **Event Store**: Persistent event storage for audit and replay capabilities

### Scalability Considerations
1. **Message Partitioning**: Distribute events across multiple queues
2. **Consumer Scaling**: Support for multiple event consumers
3. **Event Sourcing**: Full event sourcing implementation
4. **CQRS Integration**: Enhanced Command Query Responsibility Segregation

---

This event system provides a robust foundation for event-driven architecture while maintaining clean separation of concerns and testability. The three-tier classification system allows for flexible processing patterns that can adapt to different consistency and performance requirements.