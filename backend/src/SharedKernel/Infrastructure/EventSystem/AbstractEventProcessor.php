<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\EventSystem\EventInterface;
use SharedKernel\Domain\EventSystem\EventProcessorInterface;
use SharedKernel\Domain\EventSystem\EventRepositoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\EventSystem\ReceivedEvent;
use SharedKernel\Domain\Logger\LoggerInterface;
use Throwable;

/**
 * Abstract base class for event processors
 *
 * Provides a common implementation for storing and processing events.
 * Subclasses must provide the repository and event type label for logging.
 */
abstract class AbstractEventProcessor implements EventProcessorInterface
{
    protected ListenerProviderInterface $listenerProvider;
    protected LoggerInterface $logger;

    public function __construct(
        ListenerProviderInterface $listenerProvider,
        LoggerInterface $logger
    ) {
        $this->listenerProvider = $listenerProvider;
        $this->logger = $logger;
    }

    /**
     * Get the repository instance for this processor
     */
    abstract protected function getRepository(): EventRepositoryInterface;

    /**
     * Get the event type label for logging (e.g., "Outbox", "Async")
     */
    abstract protected function getEventTypeLabel(): string;

    public function store(EventInterface $event): void
    {
        try {
            $this->getRepository()->store($event);
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Failed to store %s event', strtolower($this->getEventTypeLabel())), [
                'event_type' => get_class($event),
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw $e;
        }
    }

    public function storeBatch(iterable $events): void
    {
        try {
            $this->getRepository()->storeBatch($events);
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Failed to store %s events batch', strtolower($this->getEventTypeLabel())), [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw $e;
        }
    }

    public function processEvents(): int
    {
        $receivedEvents = $this->getRepository()->getUnprocessedEvents();
        $retrievedCount = 0;

        foreach ($receivedEvents as $receivedEvent) {
            $retrievedCount++;
            $this->processReceivedEvent($receivedEvent);
        }

        return $retrievedCount;
    }

    private function processReceivedEvent(ReceivedEvent $receivedEvent): void
    {
        $event = $receivedEvent->getEvent();
        $startTime = microtime(true);

        $eventProcessed = true;
        $failedListeners = [];
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            try {
                $listener->handle($event);
            } catch (Throwable $e) {
                $this->logger->error(sprintf('%s event listener failed', $this->getEventTypeLabel()), [
                    'event_type' => get_class($event),
                    'event_id' => $event->getId(),
                    'listener' => get_class($listener),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $eventProcessed = false;
                $failedListeners[get_class($listener)] = get_class($e);
            }
        }

        $duration = microtime(true) - $startTime;

        if ($eventProcessed) {
            $this->getRepository()->markProcessed($receivedEvent);

            $this->logger->info(sprintf('%s event processed', $this->getEventTypeLabel()), [
                'event_type' => get_class($event),
                'event_id' => $event->getId(),
                'duration_ms' => round($duration * 1000, 2),
            ]);
        } else {
            $errorMessage = $this->formatErrorMessage(array_keys($failedListeners));
            $this->getRepository()->markFailed($receivedEvent, $errorMessage, $failedListeners);

            $this->logger->warning(sprintf('%s event partially failed', $this->getEventTypeLabel()), [
                'event_type' => get_class($event),
                'event_id' => $event->getId(),
                'failed_count' => count($failedListeners),
                'duration_ms' => round($duration * 1000, 2),
            ]);
        }
    }

    /**
     * @param array<int, string> $errors List of failed listener class names
     */
    private function formatErrorMessage(array $errors): string
    {
        if (count($errors) === 1) {
            return sprintf('Listener %s failed', $errors[0]);
        }

        return sprintf('%d listeners failed: %s', count($errors), implode(', ', $errors));
    }
}
