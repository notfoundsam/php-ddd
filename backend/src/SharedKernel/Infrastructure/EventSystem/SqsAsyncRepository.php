<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use Aws\Sqs\SqsClient;
use InvalidArgumentException;
use JsonException;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\EventSystem\AsyncEventInterface;
use SharedKernel\Domain\EventSystem\AsyncRepositoryInterface;
use SharedKernel\Domain\EventSystem\EventDeserializationException;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\EventInterface;
use SharedKernel\Domain\EventSystem\EventRepositoryException;
use SharedKernel\Domain\EventSystem\ReceivedEvent;
use SharedKernel\Domain\Logger\LoggerInterface;
use Throwable;

/**
 * SQS-based async event repository
 *
 * Handles queue-based event storage and retrieval via AWS SQS.
 * DLQ management is delegated to SQS infrastructure (redrive policy).
 */
final class SqsAsyncRepository implements AsyncRepositoryInterface
{
    private SqsClient $client;
    private string $queueUrl;
    private EventFactoryInterface $eventFactory;
    private LoggerInterface $logger;
    private Environment $environment;

    public function __construct(
        SqsClient $client,
        string $queueUrl,
        EventFactoryInterface $eventFactory,
        LoggerInterface $logger,
        Environment $environment
    ) {
        $this->client = $client;
        $this->queueUrl = $queueUrl;
        $this->eventFactory = $eventFactory;
        $this->logger = $logger;
        $this->environment = $environment;
    }

    public function store(EventInterface $event): void
    {
        if (!$event instanceof AsyncEventInterface) {
            throw new InvalidArgumentException(
                'Event must implement AsyncEventInterface, got: ' . get_class($event)
            );
        }

        $messageBody = $this->serializeEvent($event);

        try {
            $this->client->sendMessage([
                'QueueUrl' => $this->queueUrl,
                'MessageBody' => $messageBody,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to store async event to queue', [
                'event_type' => get_class($event),
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
            ]);
            throw EventRepositoryException::failedToStore($e);
        }
    }

    public function storeBatch(iterable $events): void
    {
        $entries = [];
        $index = 0;

        foreach ($events as $event) {
            if (!$event instanceof AsyncEventInterface) {
                throw new InvalidArgumentException(
                    'Event must implement AsyncEventInterface, got: ' . get_class($event)
                );
            }

            $entries[] = [
                'Id' => (string) $index,
                'MessageBody' => $this->serializeEvent($event),
            ];
            $index++;

            // AWS SQS batch limit
            if ($index !== AsyncEventConfig::SEND_BATCH_SIZE) {
                continue;
            }

            $this->storeBatchInternal($entries);
            $entries = [];
            $index = 0;
        }

        if (empty($entries)) {
            return;
        }

        $this->storeBatchInternal($entries);
    }

    public function getUnprocessedEvents(?int $limit = null): iterable
    {
        $limit = $limit ?? AsyncEventConfig::PROCESSING_BATCH_SIZE;

        try {
            $result = $this->client->receiveMessage([
                'QueueUrl' => $this->queueUrl,
                'MaxNumberOfMessages' => $limit,
                'WaitTimeSeconds' => AsyncEventConfig::getLongPollWaitSeconds($this->environment),
                'AttributeNames' => ['ApproximateReceiveCount'],
            ]);

            $messages = $result->get('Messages') ?? [];

            foreach ($messages as $message) {
                $receiptHandle = $message['ReceiptHandle'];
                $receiveCount = (int) ($message['Attributes']['ApproximateReceiveCount'] ?? 1);

                if ($receiveCount < AsyncEventConfig::MAX_RECEIVE_COUNT) {
                    $visibilityTimeout = AsyncEventConfig::getVisibilityTimeout($receiveCount);
                    $this->changeVisibility($receiptHandle, $visibilityTimeout);
                }

                try {
                    $event = $this->hydrateEvent($message['Body']);
                    yield new ReceivedEvent($event, $receiptHandle);
                } catch (EventDeserializationException $e) {
                    $this->logger->error('Async event deserialization failed - will go to DLQ after max retries', [
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to receive async events from queue', [
                'error' => $e->getMessage(),
            ]);
            throw EventRepositoryException::failedToGetUnprocessedEvents($e);
        }
    }

    public function markProcessed(ReceivedEvent $receivedEvent): void
    {
        try {
            $receiptHandle = $receivedEvent->getStorageContext();

            $this->client->deleteMessage([
                'QueueUrl' => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to mark async event as processed', [
                'event_type' => get_class($receivedEvent->getEvent()),
                'event_id' => $receivedEvent->getEvent()->getId(),
                'error' => $e->getMessage(),
            ]);
            throw EventRepositoryException::failedToMarkProcessed($receivedEvent->getEvent()->getId(), $e);
        }
    }

    public function markFailed(
        ReceivedEvent $receivedEvent,
        ?string $errorMessage = null,
        ?array $errorContext = null
    ): void {
        // SQS handles retry via visibility timeout and DLQ via redrive policy
        $this->logger->warning('Async event processing failed - SQS will retry or move to DLQ', [
            'event_type' => get_class($receivedEvent->getEvent()),
            'event_id' => $receivedEvent->getEvent()->getId(),
            'error_message' => $errorMessage,
        ]);
    }

    private function changeVisibility(string $receiptHandle, int $visibilityTimeoutSeconds): void
    {
        $this->client->changeMessageVisibility([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $receiptHandle,
            'VisibilityTimeout' => $visibilityTimeoutSeconds,
        ]);

        $this->logger->debug('Async event visibility timeout set', [
            'visibility_timeout' => $visibilityTimeoutSeconds,
        ]);
    }

    private function serializeEvent(EventInterface $event): string
    {
        try {
            return json_encode([
                'event_type' => get_class($event),
                'event_id' => $event->getId(),
                'event_version' => $event->getVersion(),
                'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
                'payload' => $event->serialize(),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error('Failed to serialize async event', [
                'event_type' => get_class($event),
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
            ]);
            throw EventRepositoryException::failedToSerialize(get_class($event), $event->getId(), $e);
        }
    }

    /**
     * Deserializes a queue message body into a domain event with version upcasting.
     *
     * @param string $messageBody The SQS message body
     * @return EventInterface
     * @throws EventDeserializationException
     */
    private function hydrateEvent(string $messageBody): EventInterface
    {
        $payload = json_decode($messageBody, true);

        if ($payload === null) {
            throw EventDeserializationException::failedToDecodePayload('unknown');
        }

        $eventId = $payload['event_id'] ?? 'unknown';
        $eventType = $payload['event_type'] ?? null;
        $eventVersion = (int)($payload['event_version'] ?? 1);

        if ($eventType === null) {
            throw EventDeserializationException::failedToDecodePayload($eventId);
        }

        if (!class_exists($eventType)) {
            throw EventDeserializationException::eventClassNotFound($eventType);
        }

        $eventPayload = $payload['payload'] ?? [];

        $currentVersion = $eventType::getCurrentVersion();
        if ($eventVersion < $currentVersion) {
            $this->logger->info('Upcasting async event', [
                'event_id' => $eventId,
                'from_version' => $eventVersion,
                'to_version' => $currentVersion,
            ]);

            $eventPayload = $eventType::upcastToLatestVersion($eventPayload, $eventVersion);
        }

        $eventPayload['id'] = $eventId;
        $eventPayload['version'] = $currentVersion;
        $eventPayload['occurred_at'] = $payload['occurred_at'] ?? null;

        return $this->eventFactory->createFromPayload($eventType, $eventPayload);
    }

    /**
     * @param array<int, array{Id: string, MessageBody: string}> $entries
     */
    private function storeBatchInternal(array $entries): void
    {
        try {
            $this->client->sendMessageBatch([
                'QueueUrl' => $this->queueUrl,
                'Entries' => $entries,
            ]);
        } catch (Throwable $e) {
            throw EventRepositoryException::failedToStoreBatch(count($entries), $e);
        }
    }
}
