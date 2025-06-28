<?php

$host = $_SERVER['HTTP_HOST'] ?? '';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Do not allow access to the modules directory directly
if (preg_match('/^admin/', ltrim($uri, '/'))) {
    $_SERVER['REQUEST_URI'] = str_replace(['admin'], '_404_', $uri);
}

if (preg_match('/^admin\./', $host)) {
    $_SERVER['REQUEST_URI'] = '/admin' . $_SERVER['REQUEST_URI'];
}
