<?php

declare(strict_types=1);

namespace Infrastructure\EventSystem;

use Fuel\Core\DB;
use InvalidArgumentException;
use SharedKernel\Domain\EventSystem\EventDeserializationException;
use SharedKernel\Domain\EventSystem\EventInterface;
use SharedKernel\Domain\EventSystem\EventRepositoryException;
use SharedKernel\Domain\EventSystem\EventRepositoryInterface;
use SharedKernel\Domain\EventSystem\FailedEventRepositoryInterface;
use SharedKernel\Domain\EventSystem\ReceivedEvent;
use SharedKernel\Domain\EventSystem\ScheduledEventInterface;
use SharedKernel\Infrastructure\EventSystem\ScheduledEventConfig;
use Throwable;

final class FuelPhpScheduledEventRepository extends AbstractFuelPhpEventRepository implements EventRepositoryInterface, FailedEventRepositoryInterface
{
    private const TABLE_NAME = 'scheduled_events';

    protected function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    protected function getEventTypeLabel(): string
    {
        return 'scheduled';
    }

    protected function getStatusConfig(): array
    {
        return [
            'pending' => ScheduledEventConfig::STATUS_PENDING,
            'processing' => ScheduledEventConfig::STATUS_PROCESSING,
            'processed' => ScheduledEventConfig::STATUS_PROCESSED,
            'failed' => ScheduledEventConfig::STATUS_FAILED,
            'resolved_manually' => ScheduledEventConfig::STATUS_RESOLVED_MANUALLY,
            'max_retry_attempts' => ScheduledEventConfig::MAX_RETRY_ATTEMPTS,
        ];
    }

    public function store(EventInterface $event): void
    {
        if (!$event instanceof ScheduledEventInterface) {
            throw new InvalidArgumentException(
                'Event must implement ScheduledEventInterface, got: ' . get_class($event)
            );
        }

        $payload = $this->serializeEventPayload($event);

        try {
            $data = [
                'event_id' => $event->getId(),
                'event_type' => get_class($event),
                'event_version' => $event->getVersion(),
                'payload' => $payload,
                'scheduled_for' => $event->getScheduledFor()->format('Y-m-d H:i:s'),
                'status' => ScheduledEventConfig::STATUS_PENDING,
                'created_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
                'retry_count' => 0,
            ];

            DB::insert(self::TABLE_NAME)->set($data)->execute();
        } catch (Throwable $e) {
            $this->logger->error('Failed to store scheduled event', [
                'event_id' => $event->getId(),
                'event_type' => get_class($event),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            throw EventRepositoryException::failedToStore($e);
        }
    }

    public function storeBatch(iterable $events): void
    {
        $rows = [];
        foreach ($events as $event) {
            if (!$event instanceof ScheduledEventInterface) {
                throw new InvalidArgumentException(
                    'Event must implement ScheduledEventInterface, got: ' . get_class($event)
                );
            }

            $rows[] = [
                'event_id' => $event->getId(),
                'event_type' => get_class($event),
                'event_version' => $event->getVersion(),
                'payload' => $this->serializeEventPayload($event),
                'scheduled_for' => $event->getScheduledFor()->format('Y-m-d H:i:s'),
                'status' => ScheduledEventConfig::STATUS_PENDING,
                'created_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
                'retry_count' => 0,
            ];
        }

        if (empty($rows)) {
            return;
        }

        try {
            DB::start_transaction();

            try {
                $query = DB::insert(self::TABLE_NAME, array_keys($rows[0]));
                foreach ($rows as $row) {
                    $query->values(array_values($row));
                }
                $query->execute();

                DB::commit_transaction();
            } catch (Throwable $e) {
                DB::rollback_transaction();
                throw $e;
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to store scheduled events batch', [
                'event_count' => count($rows),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw EventRepositoryException::failedToStoreBatch(count($rows), $e);
        }
    }

    public function getUnprocessedEvents(?int $limit = null): iterable
    {
        $limit = $limit ?? ScheduledEventConfig::PROCESSING_BATCH_SIZE;

        try {
            DB::start_transaction();

            $this->resetStaleProcessingEvents(ScheduledEventConfig::PROCESSING_TIMEOUT_MINUTES);

            $query = DB::query(
                "SELECT * FROM " . self::TABLE_NAME . "
                 WHERE status = '" . ScheduledEventConfig::STATUS_PENDING . "'
                 AND scheduled_for <= NOW()
                 AND (next_retry_at IS NULL OR next_retry_at <= NOW())
                 ORDER BY scheduled_for ASC
                 LIMIT :limit
                 FOR UPDATE SKIP LOCKED",
                DB::SELECT
            );

            $query->param('limit', $limit);
            $results = $query->execute()->as_array();

            if (!empty($results)) {
                $this->markEventsAsProcessing($results);
            }

            DB::commit_transaction();
        } catch (Throwable $e) {
            DB::rollback_transaction();
            $this->logger->error('Failed to get unprocessed scheduled events', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw EventRepositoryException::failedToGetUnprocessedEvents($e);
        }

        foreach ($results as $row) {
            try {
                $event = $this->hydrateEvent($row);
                yield new ReceivedEvent($event, $row['event_id']);
            } catch (EventDeserializationException $e) {
                $this->quarantinePoisonPillToDeadLetterQueue($row, $e);
            }
        }
    }

    public function getFailedEvents(?int $limit = null): iterable
    {
        $limit = $limit ?? ScheduledEventConfig::PROCESSING_BATCH_SIZE;

        try {
            $results = DB::select('*')
                ->from(self::TABLE_NAME)
                ->where('status', ScheduledEventConfig::STATUS_FAILED)
                ->order_by('failed_at', 'desc')
                ->limit($limit)->execute()->as_array();

            foreach ($results as $row) {
                yield [
                    'event_id' => $row['event_id'],
                    'event_type' => $row['event_type'],
                    'event_version' => $row['event_version'],
                    'payload' => $row['payload'],
                    'scheduled_for' => $row['scheduled_for'],
                    'status' => $row['status'],
                    'retry_count' => $row['retry_count'],
                    'created_at' => $row['created_at'],
                    'failed_at' => $row['failed_at'] ?? null,
                    'error_message' => $row['error_message'] ?? null,
                    'error_context' => $row['error_context'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to get failed scheduled events', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw EventRepositoryException::failedToGetFailedEvents($e);
        }
    }

    public function getEventById(string $eventId): ?EventInterface
    {
        try {
            $row = DB::select('*')
                ->from(self::TABLE_NAME)
                ->where('event_id', $eventId)
                ->execute()->current();

            if (!$row) {
                return null;
            }

            return $this->hydrateEvent($row);
        } catch (EventDeserializationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Failed to get scheduled event by ID', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw EventRepositoryException::failedToGetEventById($eventId, $e);
        }
    }

    /**
     * Cancels a scheduled event before it's processed
     *
     * Only events with status='pending' can be cancelled.
     *
     * @param string $eventId
     * @return bool True if cancelled, false otherwise
     */
    public function cancel(string $eventId): bool
    {
        try {
            $affected = DB::update(self::TABLE_NAME)
                ->set([
                    'status' => ScheduledEventConfig::STATUS_CANCELLED,
                    'cancelled_at' => date('Y-m-d H:i:s'),
                ])
                ->where('event_id', $eventId)
                ->where('status', ScheduledEventConfig::STATUS_PENDING)
                ->execute();

            if ($affected > 0) {
                $this->logger->info('Scheduled event cancelled', [
                    'event_id' => $eventId,
                ]);
                return true;
            }

            return false;
        } catch (Throwable $e) {
            $this->logger->error('Failed to cancel scheduled event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw EventRepositoryException::failedToCancel($eventId, $e);
        }
    }

    public function markProcessed(ReceivedEvent $receivedEvent): void
    {
        $eventId = $receivedEvent->getStorageContext();
        DB::update(self::TABLE_NAME)
            ->set(['status' => ScheduledEventConfig::STATUS_PROCESSED, 'processed_at' => date('Y-m-d H:i:s')])
            ->where('event_id', $eventId)->execute();
    }

    public function markFailed(
        ReceivedEvent $receivedEvent,
        ?string $errorMessage = null,
        ?array $errorContext = null
    ): void {
        try {
            $eventId = $receivedEvent->getStorageContext();
            $row = DB::select('retry_count')->from(self::TABLE_NAME)
                ->where('event_id', $eventId)
                ->execute()->current();

            $retryCount = $row ? (int)$row['retry_count'] + 1 : 1;

            $status = ScheduledEventConfig::STATUS_FAILED;
            $nextRetryAt = null;
            $failedAt = date('Y-m-d H:i:s');

            if ($retryCount < ScheduledEventConfig::MAX_RETRY_ATTEMPTS) {
                $status = ScheduledEventConfig::STATUS_PENDING;
                $backoffSeconds = ScheduledEventConfig::getRetryInterval($retryCount);
                $nextRetryAt = date('Y-m-d H:i:s', time() + $backoffSeconds);
                $failedAt = null;
            }

            DB::update(self::TABLE_NAME)
                ->set([
                    'status' => $status,
                    'retry_count' => $retryCount,
                    'last_retry_at' => null,
                    'next_retry_at' => $nextRetryAt,
                    'failed_at' => $failedAt,
                    'error_message' => $errorMessage,
                    'error_context' => json_encode($errorContext),
                ])
                ->where('event_id', $eventId)
                ->execute();
        } catch (Throwable $e) {
            $this->logger->error('Failed to mark scheduled event as failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw EventRepositoryException::failedToMarkFailed($eventId, $e);
        }
    }

    public function resetForRetry(string $eventId): void
    {
        DB::update(self::TABLE_NAME)
            ->set([
                'status' => ScheduledEventConfig::STATUS_PENDING,
                'retry_count' => 0,
                'next_retry_at' => null,
                'last_retry_at' => null,
                'failed_at' => null,
                'error_message' => null,
                'error_context' => null,
            ])
            ->where('event_id', $eventId)->execute();
    }

    public function markAsResolvedManually(string $eventId, string $resolvedBy): void
    {
        $data = [
            'status' => ScheduledEventConfig::STATUS_RESOLVED_MANUALLY,
            'processed_at' => date('Y-m-d H:i:s'),
            'error_message' => "Manually resolved by: $resolvedBy",
        ];

        DB::update(self::TABLE_NAME)->set($data)->where('event_id', $eventId)->execute();
    }
}
