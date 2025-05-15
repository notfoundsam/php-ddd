<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event\AwsSqs;

use Aws\Sqs\SqsClient;
use SharedKernel\Domain\Event\EventBusInterface;
use SharedKernel\Domain\Event\EventInterface;

final class AwsSqsEventBus implements EventBusInterface
{
    private SqsClient $sqs;
    private AwsEventSerializer $serializer;
    private string $queueUrl;

    public function __construct(SqsClient $sqs, AwsEventSerializer $serializer, string $queueUrl)
    {
        $this->sqs = $sqs;
        $this->serializer = $serializer;
        $this->queueUrl = $queueUrl;
    }

    public function publish(EventInterface $event): void
    {
        $data = $this->serializer->serialize($event);

        $this->sqs->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => json_encode($data),
            'MessageAttributes' => [
                'type' => [
                    'DataType' => 'String',
                    'StringValue' => $data['type'],
                ]
            ]
        ]);
    }

    public function publishAll(iterable $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }
}
