<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Async event processor interface
 *
 * Uses a best-effort delivery pattern for event processing
 * via queue storage (SQS) or synchronous dispatch (in-memory).
 */
interface AsyncEventProcessorInterface extends EventProcessorInterface
{
}
