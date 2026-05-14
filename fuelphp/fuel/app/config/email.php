<?php

return [
    'defaults' => [
        'driver' => 'ses',
        // base64 protects non-ASCII bodies from SES configuration-set rewrites
        // (open/click tracking mangles 8bit UTF-8) and avoids 8BITMIME relay issues.
        'encoding' => 'base64',
        // 'from' is intentionally unset — FuelPhpEmailNotifier sets it per message from SenderRegistry.
        'wordwrap' => null,
        'newline' => "\r\n",
    ],
];
