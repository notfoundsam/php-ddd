<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Cache;

/**
 * Extended cache interface with namespace-based versioning support
 */
interface VersionedCacheInterface extends CacheInterface
{
    /**
     * Get a version for a specific namespace
     */
    public function getNamespaceVersion(string $namespace): string;

    /**
     * Get all namespace versions
     */
    public function getNamespaceVersions(): array;
}
