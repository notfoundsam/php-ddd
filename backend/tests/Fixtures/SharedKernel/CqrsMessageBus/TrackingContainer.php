<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Container that appends "resolve:<id>" to a shared TestLog on each get().
 * Useful for asserting *when* (relative to other side effects) a handler is resolved.
 */
final class TrackingContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $entries;

    private TestLog $log;

    /**
     * @param array<string, object> $entries
     */
    public function __construct(array $entries, TestLog $log)
    {
        $this->entries = $entries;
        $this->log = $log;
    }

    public function get(string $id)
    {
        $this->log->append('resolve:' . $id);
        if (!isset($this->entries[$id])) {
            throw new class ("Service not found: $id") extends RuntimeException implements NotFoundExceptionInterface {
            };
        }
        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }
}
