<?php

declare(strict_types=1);

namespace SharedKernel\Domain\EventSystem;

/**
 * Scheduled event processor interface
 *
 * Processes events at their scheduled time using database storage
 * with support for delayed delivery and cancellation.
 */
interface ScheduledEventProcessorInterface extends EventProcessorInterface
{
}
