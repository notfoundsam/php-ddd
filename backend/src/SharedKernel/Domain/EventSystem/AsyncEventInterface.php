<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Marker interface for events that should be processed asynchronously via message queue
 *
 * Events implementing this interface will be routed to the async event processor.
 * The underlying queue implementation (SQS, in-memory for tests) is selected
 * by the environment-aware factory.
 */
interface AsyncEventInterface extends EventInterface
{
}
