<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Repository for async event operations (SQS)
 *
 * Best-effort delivery via message queue. DLQ management is handled
 * by the queue infrastructure (SQS redrive policy), not application code.
 */
interface AsyncRepositoryInterface extends EventRepositoryInterface
{
}
