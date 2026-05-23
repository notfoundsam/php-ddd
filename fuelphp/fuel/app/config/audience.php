<?php

return [
    'urls' => [
        'admin' => getenv('APP_URL_ADMIN') ?: '',
        'partner' => getenv('APP_URL_PARTNER') ?: '',
        'site' => getenv('APP_URL') ?: '',
    ],
];
