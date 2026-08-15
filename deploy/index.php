<?php

/**
 * Laravel - Front Controller Fallback for Root Directory Deployment
 */

define('LARAVEL_START', microtime(true));

// Forward root requests to public/index.php
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'
);

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
