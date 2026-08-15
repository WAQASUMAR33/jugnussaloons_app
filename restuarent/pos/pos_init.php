<?php
/**
 * POS API Initialization Helper
 * Reduces code duplication across all POS API files
 * 
 * This file handles:
 * - Error display settings
 * - Output buffering
 * - Fatal error handling
 * - Database configuration loading
 * - Connection validation
 * - CORS headers
 * 
 * Usage:
 *   require_once 'pos_init.php';
 *   // Your API code here...
 */

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["status" => "error", "message" => "Fatal error: " . $error['message']]);
        exit();
    }
});

// Start output buffering
ob_start();

// Include CORS headers (must be before any output)
require_once __DIR__ . '/../api/cors_headers.php';

// Include database configuration
try {
    include(__DIR__ . "/config.php");
} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["status" => "error", "message" => "Configuration error: " . $e->getMessage()]);
    exit();
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["status" => "error", "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}

// Check connection (check both $conn and $connection for compatibility)
if ((!isset($conn) || !$conn || (isset($conn->connect_error) && $conn->connect_error)) && 
    (!isset($connection) || !$connection)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

/**
 * Send JSON response and exit (POS style - uses 'status' instead of 'success')
 * @param string $status 'success' or 'error'
 * @param mixed $data
 * @param string $message
 * @param int $httpCode
 */
function sendPosResponse($status, $data = null, $message = '', $httpCode = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code($httpCode);
    }
    
    $response = ['status' => $status];
    if ($data !== null) {
        if (is_array($data) && isset($data[0])) {
            // If it's a numeric array, return as is (for backward compatibility)
            echo json_encode($data);
        } else {
            $response['data'] = $data;
        }
    }
    if ($message !== '') {
        $response['message'] = $message;
    }
    
    if (!isset($response['data']) || is_array($response['data'])) {
        echo json_encode($response);
    }
    exit();
}

/**
 * Get input data from request (supports GET, POST, and JSON)
 * @return array
 */
function getPosRequestData() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if ($method === 'GET') {
        return $_GET;
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!$data || !is_array($data)) {
            $data = $_POST;
        }
        return $data;
    }
}

/**
 * Clear output buffer and send error response (POS style)
 * @param string $message
 * @param int $httpCode
 */
function sendPosErrorResponse($message, $httpCode = 500) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code($httpCode);
    }
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit();
}

?>

