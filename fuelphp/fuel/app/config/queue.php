<?php

declare(strict_types=1);

/**
 * Queue configuration consumed by SqsAsyncEventProcessorFactory.
 *
 * Per-env overrides may live in app/config/{development,test}/queue.php; env
 * variables override any field at runtime via the `getenv()` calls below.
 *
 * An empty `async_events.queue_url` signals the factory to fall back to
 * InMemoryAsyncRepository (synchronous dispatch) in dev / test.
 */
return [
    'async_events' => [
        'queue_url' => getenv('SQS_ASYNC_EVENTS_QUEUE_URL') ?: '',
        'region'    => getenv('AWS_DEFAULT_REGION') ?: '',
    ],
];
