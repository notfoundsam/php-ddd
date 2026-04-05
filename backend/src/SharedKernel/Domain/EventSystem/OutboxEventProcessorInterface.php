<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Outbox event processor interface
 *
 * Uses transactional outbox pattern for reliable event processing
 * with ACID guarantees via database storage.
 */
interface OutboxEventProcessorInterface extends EventProcessorInterface
{
}
