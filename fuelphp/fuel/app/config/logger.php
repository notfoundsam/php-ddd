<?php

declare(strict_types=1);

/**
 * Logger configuration consumed by Infrastructure\Logger\MonologLoggerFactory.
 *
 * Per-env overrides live in app/config/{development,test}/logger.php; env
 * variables override any field at runtime via the `getenv()` calls below.
 *
 * Defaults match cloud-like behaviour (JSON to stdout, INFO).
 */
return [
    'channel' => getenv('LOG_CHANNEL') ?: 'php-ddd',
    'handler' => getenv('LOG_HANDLER') ?: 'stdout',
    'level'   => getenv('LOG_LEVEL') ?: 'info',
    'path'    => getenv('LOG_PATH') ?: null,
    'rotation_days' => (int) (getenv('LOG_ROTATION_DAYS') ?: 7),
];
