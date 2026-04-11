# ADR-006: CQRS Message Bus

**Status:** Accepted
**Date:** 2026-04-11

## Context

The project needs a command/query separation layer to enforce CQRS principles across bounded contexts. The bus must support cross-cutting concerns (logging, transactions, caching) without coupling business logic to infrastructure. The implementation must be framework-agnostic (PHP 7.4 compatible in `backend/src/`), work with the existing PHP-DI container, and support the ongoing FuelPHP-to-Laravel migration where each framework may need different decorator stacks.

Key requirements:
- Separate buses for commands and queries — different cross-cutting concerns per bus
- Handlers must be explicitly registered and fail fast at boot, not at dispatch time
- Each bounded context must own its handler mappings without cross-context coupling
- Decorator stacks must be composable per framework and per environment
- Query responses must carry type information through the bus to the caller

## Decision

### Two Separate Buses

Commands and queries use separate buses (`CommandBusInterface`, `QueryBusInterface`) rather than a single message bus. Commands return `void` and may have decorators for transactions and logging. Queries return typed responses and may have decorators for caching and logging. A unified bus was rejected because the different return types and decorator needs would require runtime branching or type checks.

### `__invoke` Handler Pattern

Handlers use `__invoke(SpecificCommand $command)` with specific parameter types instead of a shared `handle(CommandInterface $command)` method. The handler interfaces (`CommandHandlerInterface`, `QueryHandlerInterface`) are marker interfaces — they carry no `handle()` method.

A generic `handle(CommandInterface)` approach was considered but rejected because:
- Every handler would need a type guard (`if (!$command instanceof ...)`) or an `assert()` — boilerplate that PHP's type system already handles
- `__invoke` gives full IDE autocomplete and PHP-enforced type safety at the handler level
- The bus stores handlers as `callable`, which accepts any object with `__invoke`
- Mature PHP CQRS frameworks (Ecotone, Symfony Messenger) use this pattern as their default

The marker interfaces are retained so handlers remain identifiable by type for container configuration and static analysis.

### Explicit Handler Registration with Fail-Fast Guarantee

Handlers are registered by explicit mapping (`CommandClass => HandlerClass`) at bus construction time. The factory resolves all handlers from the DI container eagerly — if a handler class is missing or misconfigurable, the error occurs on the first request to any route, not when that specific command is dispatched.

Auto-discovery via reflection or attributes was considered but rejected because:
- PHP 7.4 does not support attributes
- Reflection-based discovery defers errors to dispatch time — a missing handler silently passes until that specific command is used
- Explicit registration makes the handler-to-command mapping visible and auditable

### Bounded-Context Handler Registries

Each bounded context provides its own handler registry implementing `CommandHandlerRegistryInterface` or `QueryHandlerRegistryInterface`:

```
Crm/Infrastructure/CqrsMessageBus/CrmCommandHandlerRegistry.php
Marketing/Infrastructure/CqrsMessageBus/MarketingCommandHandlerRegistry.php
```

The bus factory accepts an array of registries and merges them. The DI config composes which registries are active.

A centralized registry in SharedKernel was the initial implementation but was replaced because:
- It created a coupling point — every bounded context would edit the same file
- It violated bounded-context autonomy — a core modular monolith principle
- Adding a new context requires only creating a registry and adding one line to DI config

### Generic Query Response Types (PHPStan Templates)

`QueryInterface` carries a `@template TResponse of QueryResponseInterface` annotation. `QueryBusInterface::dispatch()` uses `@param QueryInterface<TResponse>` and `@return TResponse`. PHPStan resolves the concrete response type at each call site:

```php
/** @implements QueryInterface<GetUserResponse> */
final class GetUserQuery implements QueryInterface {}

// PHPStan infers: $response is GetUserResponse
$response = $this->queryBus->dispatch(new GetUserQuery($userId));
$response->getName();  // type-safe, no casting needed
```

Alternative approaches considered:
- Cast in controller with `@var` — annotation-only safety, no enforcement
- `instanceof` check in controller — boilerplate in every controller action
- Bypass the bus for queries — loses the decorator chain

PHP does not enforce generics at runtime, so the `dispatch()` return type remains `QueryResponseInterface` in the actual signature. The generic annotations are enforced by PHPStan/Psalm only.

### `QueryResponseInterface` as Marker Interface

`QueryResponseInterface` is an empty marker interface with no methods. An initial implementation included `toJson(): string` but this was removed because:
- It couples the Application layer to a specific serialization format
- Query responses consumed by CLI, background workers, or service classes don't need JSON
- Serialization is a presentation/infrastructure concern — the controller or API layer handles it

If JSON serialization is needed in the future, a `JsonSerializableResponseInterface extends QueryResponseInterface` can be added without breaking existing responses.

### DI-Driven Decorator Stacks

Bus factories return a bare bus with registered handlers. Decorator wrapping is the DI config's responsibility:

```php
CommandBusInterface::class => DI\factory(function(ContainerInterface $c) {
    $bus = (new CommandBusFactory($c, [...]))->__invoke();
    $bus = new CommandLoggerDecorator($bus, $c->get(LoggerInterface::class));
    $bus = new CommandTransactionDecorator($bus, ...);
    return $bus;
}),
```

Hardcoding decorators in the factory was the initial implementation but was replaced because:
- Each framework (FuelPHP, Laravel) may need different decorator stacks
- Environment-specific decorators (debug in dev, skip in prod) require conditional logic
- The project already follows this pattern — `VersionedCacheDecorator` is composed in DI config, not in `CacheFactory`
- Adding a new decorator requires no factory code changes

### Logger Decorators

Logger decorators contain only logging logic:
- Message identification: `message_type` (command/query), `message_class` (FQCN)
- Outcome: `success` flag, `execution_time_ms`
- Slow execution warning with configurable threshold (constructor parameter, defaults: 2.0s commands, 0.5s queries)
- On failure: `error` message, `error_class`, `error_file`, `error_line`

Memory metrics (`memory_get_peak_usage`) were removed because the value is process-global — in FPM workers or long-running event processors, the peak only ever grows and does not reflect the memory impact of a specific dispatch. Memory monitoring belongs in APM tooling.

Log levels: commands log success at `info` (state mutations worth tracking), queries at `debug` (high volume). Slow commands warn at `warning`, slow queries at `notice`.

### `HandlerNotFoundException extends LogicException`

A missing handler is a programmer error (misconfigured registry), not a runtime condition. The dedicated exception:
- Extends `LogicException` (signals "fix your code"), not `InvalidArgumentException` or `RuntimeException`
- Provides named constructors: `forCommand()`, `forQuery()`
- Is catchable specifically, unlike bare `InvalidArgumentException`
- Follows the project convention of dedicated exception hierarchies (e.g., `CacheException`, `StorageException`)

## Components

| Component | Layer | Purpose |
|---|---|---|
| `CommandInterface` | Application | Marker interface for commands |
| `CommandBusInterface` | Application | Dispatch contract: `dispatch(CommandInterface): void` |
| `CommandHandlerInterface` | Application | Marker interface for command handlers |
| `QueryInterface` | Application | Generic marker: `@template TResponse of QueryResponseInterface` |
| `QueryBusInterface` | Application | Dispatch contract with generic return type |
| `QueryHandlerInterface` | Application | Marker interface for query handlers |
| `QueryResponseInterface` | Application | Marker interface for query response DTOs |
| `CommandBus` | Infrastructure | Handler registry, dispatches via `callable` |
| `QueryBus` | Infrastructure | Handler registry, dispatches via `callable` |
| `CommandBusFactory` | Infrastructure | Builds bus from registries, resolves handlers from DI container |
| `QueryBusFactory` | Infrastructure | Builds bus from registries, resolves handlers from DI container |
| `CommandHandlerRegistryInterface` | Infrastructure | Contract for bounded-context command handler mappings |
| `QueryHandlerRegistryInterface` | Infrastructure | Contract for bounded-context query handler mappings |
| `CommandLoggerDecorator` | Infrastructure | Logging decorator with configurable slow threshold |
| `QueryLoggerDecorator` | Infrastructure | Logging decorator with configurable slow threshold |
| `HandlerNotFoundException` | Infrastructure | `LogicException` for missing handler registrations |

## Consequences

### Positive
- `__invoke` pattern gives PHP-enforced type safety at the handler level — no guards or casts
- Explicit registration with eager resolution fails fast at boot, not at dispatch time
- Bounded-context registries preserve autonomy — no cross-context file edits
- DI-driven decoration allows framework-specific and environment-specific stacks
- PHPStan generics carry response types through the bus — controllers get full type safety
- Logger decorators are focused — only logging, no mixed concerns
- Configurable thresholds allow tuning per environment via DI config

### Trade-offs
- PHPStan generics are annotation-only — PHP does not enforce them at runtime
- Eager handler resolution instantiates all handlers at boot, but PHP-DI compilation mitigates the cost
- Marker interfaces add no compile-time contract — handler `__invoke` signatures are not enforced by the interface

### Open Items
- Transaction decorator for command bus — wraps handler execution in a database transaction
- Cache decorator for query bus — caches responses by query identity
- Throttle decorator — rate limits bus dispatches
- Integration with event system (ADR-005) — command handler decorator to collect domain events from aggregates and store in outbox atomically
