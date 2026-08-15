<?php
/**
 * CORS Headers Configuration - Universal Version
 * 
 * Location: C:\wamp64\www\restuarent\api\cors_headers.php
 */

// 1. DYNAMIC ORIGIN HANDLING
// Instead of a static '*', this mirrors the requester's origin.
// This is more reliable for different networks and allows Credentials (Cookies/Auth).
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
} else {
    // Fallback for tools like Postman or direct browser hits
    header("Access-Control-Allow-Origin: *");
}

// 2. GLOBAL CORS HEADERS
if (!headers_sent()) {
    // Methods allowed
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    
    // Headers allowed (added common ones like X-Auth-Token or Custom-Headers)
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
    
    // Cache the preflight for 24 hours
    header('Access-Control-Max-Age: 86400');
}

// 3. HANDLE PREFLIGHT (OPTIONS) REQUEST
// This is the CRITICAL part for cross-network requests.
// Browsers check this before sending the actual POST/PUT request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
    }
    
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    
    // Return 200 and exit immediately so no other code runs
    http_response_code(200);
    exit();
}

// 4. DEFAULT CONTENT TYPE
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// 5. PRODUCTION ERROR HANDLING (Recommended)
// Uncomment these in production to prevent HTML errors from breaking your JSON response
// ini_set('display_errors', 0);
// error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

?>