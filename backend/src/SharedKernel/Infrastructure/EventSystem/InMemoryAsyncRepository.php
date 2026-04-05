<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\EventSystem\AsyncRepositoryInterface;
use SharedKernel\Domain\EventSystem\EventInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\EventSystem\ReceivedEvent;
use SharedKernel\Domain\Logger\LoggerInterface;
use Throwable;

/**
 * In-memory async event repository for testing
 *
 * Unlike SQS-based implementation, this dispatches events synchronously
 * during store() for immediate testing feedback.
 */
final class InMemoryAsyncRepository implements AsyncRepositoryInterface
{
    private ListenerProviderInterface $listenerProvider;
    private LoggerInterface $logger;

    /** @var array<EventInterface> */
    private array $events = [];

    public function __construct(
        ListenerProviderInterface $listenerProvider,
        LoggerInterface $logger
    ) {
        $this->listenerProvider = $listenerProvider;
        $this->logger = $logger;
    }

    public function store(EventInterface $event): void
    {
        // Dispatch synchronously for testing
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            try {
                $listener->handle($event);
            } catch (Throwable $e) {
                $this->logger->error('Async event listener failed', [
                    'event_type' => get_class($event),
                    'event_id' => $event->getId(),
                    'listener' => get_class($listener),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $this->events[] = $event;
    }

    public function storeBatch(iterable $events): void
    {
        foreach ($events as $event) {
            $this->store($event);
        }
    }

    public function getUnprocessedEvents(?int $limit = null): iterable
    {
        return [];
    }

    public function markProcessed(ReceivedEvent $receivedEvent): void
    {
        // No-op for in-memory
    }

    public function markFailed(
        ReceivedEvent $receivedEvent,
        ?string $errorMessage = null,
        ?array $errorContext = null
    ): void {
        // No-op for in-memory
    }

    /**
     * For testing: get all dispatched events
     *
     * @return array<EventInterface>
     */
    public function getDispatchedEvents(): array
    {
        return $this->events;
    }

    /**
     * For testing: clear all events
     */
    public function clear(): void
    {
        $this->events = [];
    }
}
