<?php

declare(strict_types=1);

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
