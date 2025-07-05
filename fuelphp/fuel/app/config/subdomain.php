<?php

$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Restrict access to the modules directory directly
if (preg_match('#^admin(/|$)#', ltrim($uri, '/'))) {
    $_SERVER['REQUEST_URI'] = '/_404_';
}

// Route requests to the modules directory
if (preg_match('/^admin\./', $host)) {
    $_SERVER['REQUEST_URI'] = '/admin' . $uri;
}
