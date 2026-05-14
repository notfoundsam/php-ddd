<?php

return [
    'defaults' => [
        'driver' => 'smtp',
        // 'from' is intentionally unset — FuelPhpEmailNotifier sets it per message from SenderRegistry.
        'smtp' => [
            'host' => 'mail',
            'port' => 1025,
            'timeout' => 30,
            'starttls' => false,
        ],
    ],
];
