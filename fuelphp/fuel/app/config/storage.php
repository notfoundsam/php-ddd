<?php

declare(strict_types=1);

/**
 * Storage configuration consumed by Infrastructure\Storage\StorageFactory.
 *
 * Per-env overrides live in app/config/{development,test}/storage.php; env
 * variables override any field at runtime via the `getenv()` calls below.
 *
 * Defaults match cloud-like behaviour (driver=s3, bucket/region from env).
 */
return [
    'driver' => getenv('STORAGE_DRIVER') ?: 's3',
    'local'  => [
        'root' => getenv('STORAGE_LOCAL_ROOT') ?: '/app/storage',
    ],
    's3' => [
        'bucket' => getenv('AWS_BUCKET') ?: null,
        'region' => getenv('AWS_DEFAULT_REGION') ?: null,
    ],
];
