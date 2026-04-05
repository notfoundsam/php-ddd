<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

final class OutboxEventConfig
{
    /**
     * Event status values (must match database ENUM)
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RESOLVED_MANUALLY = 'resolved_manually';

    /**
     * Maximum number of retry attempts before marking as permanently failed
     */
    public const MAX_RETRY_ATTEMPTS = 5;

    /**
     * Retry intervals in seconds for exponential backoff
     *
     * Gradual progression from 20 seconds to 15 minutes.
     * Matches an async pattern for consistency.
     *
     * 1st retry: 20 seconds (quick first retry)
     * 2nd retry: 2 minutes
     * 3rd retry: 8 minutes
     * 4th retry: 15 minutes
     * 5th retry: 15 minutes (max, before DLQ)
     */
    public const RETRY_INTERVALS = [
        1 => 20,
        2 => 120,
        3 => 480,
        4 => 900,
        5 => 900,
    ];

    /**
     * Processing timeout in minutes
     * Events stuck in 'processing' longer than this are reset to 'pending'
     *
     * Adjust based on your slowest expected listener processing time
     */
    public const PROCESSING_TIMEOUT_MINUTES = 2;

    /**
     * Default batch size for processing events
     *
     * Smaller batches = faster recovery from poison pills, more frequent commits,
     * and safer graceful deployments within 60s timeout window
     */
    public const PROCESSING_BATCH_SIZE = 20;

    /**
     * Get retry interval for a given attempt number
     *
     * @param int $retryCount The retry attempt number
     * @return int Seconds to wait before retry
     */
    public static function getRetryInterval(int $retryCount): int
    {
        return self::RETRY_INTERVALS[$retryCount] ?? 900;
    }
}
