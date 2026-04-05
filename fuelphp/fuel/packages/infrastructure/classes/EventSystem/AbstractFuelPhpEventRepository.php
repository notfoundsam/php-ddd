<?php

declare(strict_types=1);

namespace Infrastructure\EventSystem;

use Fuel\Core\DB;
use JsonException;
use SharedKernel\Domain\EventSystem\AbstractEvent;
use SharedKernel\Domain\EventSystem\EventDeserializationException;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\EventInterface;
use SharedKernel\Domain\EventSystem\EventRepositoryException;
use SharedKernel\Domain\Logger\LoggerInterface;

abstract class AbstractFuelPhpEventRepository
{
    protected EventFactoryInterface $eventFactory;
    protected LoggerInterface $logger;

    public function __construct(EventFactoryInterface $eventFactory, LoggerInterface $logger)
    {
        $this->eventFactory = $eventFactory;
        $this->logger = $logger;
    }

    abstract protected function getTableName(): string;

    abstract protected function getEventTypeLabel(): string;

    /**
     * @return array<string, int|string>
     */
    abstract protected function getStatusConfig(): array;

    /**
     * Serializes event payload to JSON
     *
     * @param EventInterface $event
     * @return string
     * @throws EventRepositoryException
     */
    protected function serializeEventPayload(EventInterface $event): string
    {
        try {
            return json_encode($event->serialize(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error('Failed to serialize ' . $this->getEventTypeLabel() . ' event', [
                'event_type' => get_class($event),
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
            ]);
            throw EventRepositoryException::failedToSerialize(get_class($event), $event->getId(), $e);
        }
    }

    /**
     * Hydrates a database row into an event object
     *
     * @param array<string, mixed> $row
     * @return EventInterface
     * @throws EventDeserializationException
     */
    protected function hydrateEvent(array $row): EventInterface
    {
        /** @var class-string<AbstractEvent> $eventType */
        $eventType = $row['event_type'];
        $eventVersion = (int)$row['event_version'];
        $payload = json_decode($row['payload'], true);

        if ($payload === null) {
            throw EventDeserializationException::failedToDecodePayload($row['event_id']);
        }

        if (!class_exists($eventType)) {
            throw EventDeserializationException::eventClassNotFound($eventType);
        }

        $currentVersion = $eventType::getCurrentVersion();
        if ($eventVersion < $currentVersion) {
            $this->logger->info('Upcasting ' . $this->getEventTypeLabel() . ' event', [
                'event_id' => $row['event_id'],
                'from_version' => $eventVersion,
                'to_version' => $currentVersion,
            ]);

            $payload = $eventType::upcastToLatestVersion($payload, $eventVersion);
        }

        $payload['id'] = $row['event_id'];
        $payload['version'] = $currentVersion;
        $payload['occurred_at'] = $row['created_at'];

        return $this->eventFactory->createFromPayload($eventType, $payload);
    }

    /**
     * Atomically marks events as 'processing' status
     *
     * @param array<int, array<string, mixed>> $results
     * @return void
     */
    protected function markEventsAsProcessing(array $results): void
    {
        if (!empty($results)) {
            $eventIds = array_column($results, 'event_id');
            $config = $this->getStatusConfig();
            DB::update($this->getTableName())
                ->set(['status' => $config['processing'], 'last_retry_at' => date('Y-m-d H:i:s')])
                ->where('event_id', 'IN', $eventIds)
                ->execute();
        }
    }

    /**
     * Quarantines a poison pill event to Dead Letter Queue
     *
     * @param array<string, mixed> $row
     * @param EventDeserializationException $exception
     * @return void
     */
    protected function quarantinePoisonPillToDeadLetterQueue(
        array $row,
        EventDeserializationException $exception
    ): void {
        $config = $this->getStatusConfig();

        $this->logger->error($this->getEventTypeLabel() . ' event deserialization failed - quarantining to DLQ', [
            'event_id' => $row['event_id'],
            'event_type' => $row['event_type'],
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
        ]);

        DB::update($this->getTableName())
            ->set([
                'status' => $config['failed'],
                'retry_count' => $config['max_retry_attempts'],
                'last_retry_at' => null,
                'next_retry_at' => null,
                'failed_at' => date('Y-m-d H:i:s'),
                'error_message' => 'Deserialization failed: ' . $exception->getMessage(),
                'error_context' => json_encode([
                    'exception' => get_class($exception),
                    'payload' => $row['payload'],
                ]),
            ])
            ->where('event_id', $row['event_id'])
            ->execute();
    }

    /**
     * Resets events stuck in 'processing' status (from crashed workers) back to 'pending'
     *
     * Called inside the same transaction as getUnprocessedEvents() to avoid
     * a race window between the reset and the SELECT ... FOR UPDATE SKIP LOCKED.
     *
     * @param int $timeoutMinutes Events in 'processing' longer than this are considered stale
     */
    protected function resetStaleProcessingEvents(int $timeoutMinutes): void
    {
        $config = $this->getStatusConfig();
        $cutoffTime = date('Y-m-d H:i:s', time() - ($timeoutMinutes * 60));

        $result = DB::update($this->getTableName())
            ->set(['status' => $config['pending']])
            ->where('status', $config['processing'])
            ->where('last_retry_at', '<', $cutoffTime)
            ->execute();

        if ($result > 0) {
            $this->logger->warning('Reset stale processing ' . $this->getEventTypeLabel() . ' events', [
                'count' => $result,
                'timeout_minutes' => $timeoutMinutes,
            ]);
        }
    }
}
