<?php
/**
 * Print Kitchen Receipt API (Unified Endpoint)
 * Automatically prints kitchen receipts (KOT) to the correct printer based on kitchen_id
 * 
 * This endpoint works from both api/ and pos/ directories
 * Called automatically when orders are created
 * 
 * POST Parameters:
 * - order_id (int, required) - Order ID
 * - kitchen_id (int, required) - Kitchen ID
 * - branch_id (int, optional) - Branch ID
 * 
 * Returns:
 * {
 *   "success": true,
 *   "message": "Receipt sent to printer successfully",
 *   "printer_ip": "192.168.1.101",
 *   "kitchen_name": "Fast Food Kitchen"
 * }
 */

// This is the SINGLE unified endpoint for kitchen printing
// Always located in api/ directory - no need to check location
require_once 'cors_headers.php';

// Disable error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

ob_start();

try {
    include('config.php');
} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}

if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

try {
    // Get input data - support both direct include and HTTP POST
    $input = null;
    
    // Try JSON input first (for HTTP requests)
    $raw_input = @file_get_contents('php://input');
    if ($raw_input) {
        $input = json_decode($raw_input, true);
    }
    
    // Fallback to POST data (for direct includes or form posts)
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    // If still no input, try GET (for testing)
    if (!$input || !is_array($input)) {
        $input = $_GET;
    }
    
    $order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
    $kitchen_id = isset($input['kitchen_id']) ? intval($input['kitchen_id']) : 0;
    $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
    
    // Use the unified print function (avoids code duplication)
    require_once __DIR__ . '/print_kitchen_function.php';
    
    $print_response = print_kitchen_receipt_direct($connection, $order_id, $kitchen_id, $branch_id);
    
    // Return JSON response
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Ensure message is always a string
    if (isset($print_response['message']) && !is_string($print_response['message'])) {
        $print_response['message'] = (string)$print_response['message'];
    }
    if (!isset($print_response['message'])) {
        $print_response['message'] = $print_response['success'] ? 'Print completed' : 'Print failed';
    }
    
    echo json_encode($print_response);
    
} catch (Exception $e) {
    error_log("Print Kitchen Receipt Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    $error_msg = $e->getMessage();
    echo json_encode([
        "success" => false,
        "message" => is_string($error_msg) ? $error_msg : "An error occurred while printing"
    ]);
    exit();
} catch (Error $e) {
    error_log("Print Kitchen Receipt Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    $error_msg = $e->getMessage();
    echo json_encode([
        "success" => false,
        "message" => is_string($error_msg) ? $error_msg : "A fatal error occurred while printing"
    ]);
    exit();
}

exit();
?>

