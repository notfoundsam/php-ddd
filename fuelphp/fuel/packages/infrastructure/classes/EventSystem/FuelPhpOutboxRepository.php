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
use SharedKernel\Domain\EventSystem\OutboxEventInterface;
use SharedKernel\Domain\EventSystem\ReceivedEvent;
use SharedKernel\Infrastructure\EventSystem\OutboxEventConfig;
use Throwable;

final class FuelPhpOutboxRepository extends AbstractFuelPhpEventRepository implements EventRepositoryInterface, FailedEventRepositoryInterface
{
    private const TABLE_NAME = 'outbox_events';

    protected function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    protected function getEventTypeLabel(): string
    {
        return 'outbox';
    }

    protected function getStatusConfig(): array
    {
        return [
            'pending' => OutboxEventConfig::STATUS_PENDING,
            'processing' => OutboxEventConfig::STATUS_PROCESSING,
            'processed' => OutboxEventConfig::STATUS_PROCESSED,
            'failed' => OutboxEventConfig::STATUS_FAILED,
            'resolved_manually' => OutboxEventConfig::STATUS_RESOLVED_MANUALLY,
            'max_retry_attempts' => OutboxEventConfig::MAX_RETRY_ATTEMPTS,
        ];
    }

    public function store(EventInterface $event): void
    {
        if (!$event instanceof OutboxEventInterface) {
            throw new InvalidArgumentException(
                'Event must implement OutboxEventInterface, got: ' . get_class($event)
            );
        }

        $payload = $this->serializeEventPayload($event);

        try {
            $data = [
                'event_id' => $event->getId(),
                'event_type' => get_class($event),
                'event_version' => $event->getVersion(),
                'payload' => $payload,
                'status' => OutboxEventConfig::STATUS_PENDING,
                'created_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
                'retry_count' => 0,
            ];

            DB::insert(self::TABLE_NAME)->set($data)->execute();
        } catch (Throwable $e) {
            $this->logger->error('Failed to store outbox event', [
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
            if (!$event instanceof OutboxEventInterface) {
                throw new InvalidArgumentException(
                    'Event must implement OutboxEventInterface, got: ' . get_class($event)
                );
            }

            $rows[] = [
                'event_id' => $event->getId(),
                'event_type' => get_class($event),
                'event_version' => $event->getVersion(),
                'payload' => $this->serializeEventPayload($event),
                'status' => OutboxEventConfig::STATUS_PENDING,
                'created_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
                'retry_count' => 0,
            ];
        }

        if (empty($rows)) {
            return;
        }

        try {
            $query = DB::insert(self::TABLE_NAME, array_keys($rows[0]));
            foreach ($rows as $row) {
                $query->values(array_values($row));
            }
            $query->execute();
        } catch (Throwable $e) {
            $this->logger->error('Failed to store outbox events batch', [
                'event_count' => count($rows),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw EventRepositoryException::failedToStoreBatch(count($rows), $e);
        }
    }

    public function getUnprocessedEvents(?int $limit = null): iterable
    {
        $limit = $limit ?? OutboxEventConfig::PROCESSING_BATCH_SIZE;

        try {
            DB::start_transaction();

            $this->resetStaleProcessingEvents(OutboxEventConfig::PROCESSING_TIMEOUT_MINUTES);

            $query = DB::query(
                "SELECT * FROM " . self::TABLE_NAME . "
                 WHERE status = '" . OutboxEventConfig::STATUS_PENDING . "'
                 AND (next_retry_at IS NULL OR next_retry_at <= NOW())
                 ORDER BY created_at ASC
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
            $this->logger->error('Failed to get unprocessed outbox events', [
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
        $limit = $limit ?? OutboxEventConfig::PROCESSING_BATCH_SIZE;

        try {
            $results = DB::select('*')
                ->from(self::TABLE_NAME)
                ->where('status', OutboxEventConfig::STATUS_FAILED)
                ->order_by('failed_at', 'desc')
                ->limit($limit)->execute()->as_array();

            foreach ($results as $row) {
                yield [
                    'event_id' => $row['event_id'],
                    'event_type' => $row['event_type'],
                    'event_version' => $row['event_version'],
                    'payload' => $row['payload'],
                    'status' => $row['status'],
                    'retry_count' => $row['retry_count'],
                    'created_at' => $row['created_at'],
                    'failed_at' => $row['failed_at'] ?? null,
                    'error_message' => $row['error_message'] ?? null,
                    'error_context' => $row['error_context'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to get failed outbox events', [
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
            $this->logger->error('Failed to get outbox event by ID', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            throw EventRepositoryException::failedToGetEventById($eventId, $e);
        }
    }

    public function markProcessed(ReceivedEvent $receivedEvent): void
    {
        $eventId = $receivedEvent->getStorageContext();
        DB::update(self::TABLE_NAME)
            ->set(['status' => OutboxEventConfig::STATUS_PROCESSED, 'processed_at' => date('Y-m-d H:i:s')])
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

            $status = OutboxEventConfig::STATUS_FAILED;
            $nextRetryAt = null;
            $failedAt = date('Y-m-d H:i:s');

            if ($retryCount < OutboxEventConfig::MAX_RETRY_ATTEMPTS) {
                $status = OutboxEventConfig::STATUS_PENDING;
                $backoffSeconds = OutboxEventConfig::getRetryInterval($retryCount);
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
            $this->logger->error('Failed to mark outbox event as failed', [
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
                'status' => OutboxEventConfig::STATUS_PENDING,
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
            'status' => OutboxEventConfig::STATUS_RESOLVED_MANUALLY,
            'processed_at' => date('Y-m-d H:i:s'),
            'error_message' => "Manually resolved by: $resolvedBy",
        ];

        DB::update(self::TABLE_NAME)->set($data)->where('event_id', $eventId)->execute();
    }
}
