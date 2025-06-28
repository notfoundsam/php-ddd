<?php

$host = $_SERVER['HTTP_HOST'] ?? '';

if (preg_match('/^admin\./', $host)) {
    $_SERVER['REQUEST_URI'] = '/admin' . $_SERVER['REQUEST_URI'];
}
