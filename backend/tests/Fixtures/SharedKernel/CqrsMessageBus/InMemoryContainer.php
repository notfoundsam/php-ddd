<?php

declare(strict_types=1);

namespace Tests\Fixtures\SharedKernel\CqrsMessageBus;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

final class InMemoryContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $services;

    /**
     * @param array<string, object> $services
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new class ("Service not found: $id") extends RuntimeException implements NotFoundExceptionInterface {
            };
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
