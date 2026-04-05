<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use SharedKernel\Domain\Environment;

/**
 * Configuration constants for async event processing
 *
 * Similar to OutboxEventConfig, centralizes async repository settings.
 */
final class AsyncEventConfig
{
    /**
     * Maximum receive count before a message goes to DLQ
     *
     * This is configured at the SQS/ElasticMQ queue level (maxReceiveCount).
     * After this many receives, a message is automatically moved to DLQ.
     */
    public const MAX_RECEIVE_COUNT = 5;

    /**
     * Visibility timeout per receive count (exponential backoff)
     *
     * Applied when a message is received, based on ApproximateReceiveCount.
     * Longer timeouts for repeated failures prevent rapid retry loops.
     *
     * Gradual progression from 20 seconds to 15 minutes.
     * SQS max visibility timeout is 12 hours (43,200 seconds).
     *
     * 1st receive: 20 seconds (quick first retry)
     * 2nd receive: 2 minutes
     * 3rd receive: 8 minutes
     * 4th receive: 15 minutes (max, before DLQ on 5th receive)
     */
    public const VISIBILITY_TIMEOUTS = [
        1 => 20,
        2 => 120,
        3 => 480,
        4 => 900,
    ];

    /**
     * Long polling wait time in seconds for production
     *
     * Controls how long receive() waits for messages before returning empty.
     * Max: 20 seconds for SQS.
     */
    private const LONG_POLL_WAIT_SECONDS_PRODUCTION = 20;

    /**
     * Long polling wait time in seconds for development
     *
     * Shorter timeout for faster shutdown during development.
     */
    private const LONG_POLL_WAIT_SECONDS_DEVELOPMENT = 5;

    /**
     * Default batch size for processing events
     *
     * Set to SQS maximum of 10 messages per receive call.
     * Cannot be increased beyond this AWS-enforced limit.
     */
    public const PROCESSING_BATCH_SIZE = 10;

    /**
     * Batch size for sending messages
     *
     * SQS maximum is 10 messages per send batch.
     * This limit is enforced by AWS and cannot be increased.
     */
    public const SEND_BATCH_SIZE = 10;

    /**
     * Get a long polling wait time based on environment
     *
     * Returns a shorter timeout for development (fast shutdowns)
     * and a longer timeout for production (optimal efficiency).
     *
     * @param Environment $environment
     * @return int Wait time in seconds
     */
    public static function getLongPollWaitSeconds(Environment $environment): int
    {
        return $environment->isDevelopment()
            ? self::LONG_POLL_WAIT_SECONDS_DEVELOPMENT
            : self::LONG_POLL_WAIT_SECONDS_PRODUCTION;
    }

    /**
     * Get visibility timeout based on receive count
     *
     * Used for exponential backoff - higher receive counts get longer timeouts.
     *
     * @param int $receiveCount The ApproximateReceiveCount from the message
     * @return int Visibility timeout in seconds
     */
    public static function getVisibilityTimeout(int $receiveCount): int
    {
        return self::VISIBILITY_TIMEOUTS[$receiveCount] ?? self::VISIBILITY_TIMEOUTS[4];
    }
}
