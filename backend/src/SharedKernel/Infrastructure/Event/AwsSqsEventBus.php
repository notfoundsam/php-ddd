<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event;

use Aws\Sqs\SqsClient;
use Aws\Sqs\Exception\SqsException;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\PostCommitEventInterface;

final class AwsSqsEventBus implements EventBusInterface
{
    private SqsClient $sqs;

    private string $queueUrl;

    public function __construct(SqsClient $sqs, string $queueUrl)
    {
        $this->sqs = $sqs;
        $this->queueUrl = $queueUrl;
    }

    public function publish(PostCommitEventInterface ...$events): void
    {
        foreach ($events as $singleEvent) {
            $this->publishEvent($singleEvent);
        }
    }

    private function publishEvent(PostCommitEventInterface $event): void
    {
        $groupId = get_class($event);

        try {
            $this->sqs->sendMessage([
                'QueueUrl' => $this->queueUrl,
                'MessageBody' => json_encode($event->serialize()),
                'MessageAttributes' => [
                    'type' => [
                        'DataType' => 'String',
                        'StringValue' => $groupId,
                    ],
                ],
                'MessageGroupId' => $groupId,
                'MessageDeduplicationId' => $event->getId(),
            ]);
        } catch (SqsException $e) {
            // Log error and potentially implement retry logic or dead letter queue
            error_log(sprintf('Failed to publish event %s: %s', get_class($event), $e->getMessage()));
            throw $e;
        }
    }
}
