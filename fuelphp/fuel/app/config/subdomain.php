<?php

$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Load-bearing: with FuelPHP modules removed (ADR-013), routes `admin => admin/welcome/index`
// and `partner => partner/welcome/index` live in the global routes file. Without this 404
// rewrite, `https://php-ddd.test/admin` (main host) would resolve to Controller_Admin_Welcome.
// Audience boundaries are enforced here by host, not by the router.
if (preg_match('#^(admin|partner)(/|$)#', ltrim($uri, '/'))) {
    $_SERVER['REQUEST_URI'] = '/_404_';
}

// Route requests by subdomain to the audience-prefixed controllers.
if (preg_match('/^admin\./', $host)) {
    $_SERVER['REQUEST_URI'] = '/admin' . $uri;
}

if (preg_match('/^partner\./', $host)) {
    $_SERVER['REQUEST_URI'] = '/partner' . $uri;
}
