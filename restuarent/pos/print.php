<?php
/**
 * Print KOT (Kitchen Order Ticket) - Batch Print All Kitchens
 * 
 * PURPOSE: Prints KOT receipts to ALL kitchens for an order (NOT customer bills)
 * - Automatically detects all kitchens that have items in the order
 * - Prints to each kitchen's network printer
 * - Returns summary of all print results
 * 
 * This is DIFFERENT from api/print.php which prints customer receipts/bills
 * This is a CONVENIENCE wrapper that prints to all kitchens at once
 * Uses api/print_kitchen_function.php for KOT printing
 * 
 * POST Parameters:
 * - id (int, required) - Order ID
 * 
 * Returns:
 * {
 *   "success": true,
 *   "message": "Printed to 3 kitchen(s), 0 error(s)",
 *   "results": [
 *     {"kitchen_id": 10, "success": true, "message": "...", "kitchen_name": "...", "printer_ip": "..."},
 *     ...
 *   ],
 *   "order_id": 123
 * }
 */

require_once '../api/cors_headers.php';

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
        echo json_encode(["success" => false, "message" => "Fatal error: " . $error['message']]);
        exit();
    }
});

// Start output buffering
ob_start();

// Include config
try {
    include("config.php");
} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}

// Check database connection
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
    // Get input - support both POST and JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $order_id = isset($input['id']) ? intval($input['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
    
    if ($order_id <= 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order ID'
        ]);
        exit();
    }
    
    // Get order details to find branch_id
    $order_sql = "SELECT order_id, branch_id FROM orders WHERE order_id = ?";
    $order_stmt = mysqli_prepare($connection, $order_sql);
    if (!$order_stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($order_stmt, "i", $order_id);
    mysqli_stmt_execute($order_stmt);
    $order_result = mysqli_stmt_get_result($order_stmt);
    
    if (mysqli_num_rows($order_result) === 0) {
        mysqli_stmt_close($order_stmt);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]);
        exit();
    }
    
    $order_data = mysqli_fetch_assoc($order_result);
    mysqli_stmt_close($order_stmt);
    
    $branch_id = $order_data['branch_id'] ?? null;
    
    // Get all kitchens that have items for this order
    // Check if order_items table exists
    $check_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
    $has_order_items = ($check_table && mysqli_num_rows($check_table) > 0);
    
    $kitchens_to_print = [];
    
    if ($has_order_items) {
        // Use order_items table
        $kitchens_sql = "
            SELECT DISTINCT k.kitchen_id
            FROM order_items oi
            INNER JOIN dishes d ON oi.dish_id = d.dish_id
            INNER JOIN categories c ON d.category_id = c.category_id
            INNER JOIN kitchens k ON c.kid = k.kitchen_id
            WHERE oi.order_id = ? AND oi.is_cancel = 0
        ";
    } else {
        // Fallback to orderdetails table
        $orderid_str = 'ORD-' . $order_id;
        $kitchens_sql = "
            SELECT DISTINCT k.kitchen_id
            FROM orderdetails od
            INNER JOIN dishes d ON od.p_id = d.dish_id
            INNER JOIN categories c ON d.category_id = c.category_id
            INNER JOIN kitchens k ON c.kid = k.kitchen_id
            WHERE od.orderid = ?
        ";
    }
    
    $kitchens_stmt = mysqli_prepare($connection, $kitchens_sql);
    if ($kitchens_stmt) {
        if ($has_order_items) {
            mysqli_stmt_bind_param($kitchens_stmt, "i", $order_id);
        } else {
            mysqli_stmt_bind_param($kitchens_stmt, "s", $orderid_str);
        }
        
        if (mysqli_stmt_execute($kitchens_stmt)) {
            $kitchens_result = mysqli_stmt_get_result($kitchens_stmt);
            while ($kitchen_row = mysqli_fetch_assoc($kitchens_result)) {
                $kitchens_to_print[] = intval($kitchen_row['kitchen_id']);
            }
        }
        mysqli_stmt_close($kitchens_stmt);
    }
    
    if (empty($kitchens_to_print)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode([
            'success' => false,
            'message' => 'No kitchens found for this order'
        ]);
        exit();
    }
    
    // Call the print function directly for each kitchen (avoids HTTP/CORS issues)
    require_once __DIR__ . '/../api/print_kitchen_function.php';
    
    $results = [];
    $success_count = 0;
    $error_count = 0;
    
    foreach ($kitchens_to_print as $kitchen_id) {
        // Call print function directly (no HTTP needed)
        $print_response = print_kitchen_receipt_direct($connection, $order_id, $kitchen_id, $branch_id);
        
        // Ensure message is always a string
        $response_message = '';
        if (isset($print_response['message'])) {
            $response_message = is_string($print_response['message']) ? $print_response['message'] : (string)$print_response['message'];
        } else {
            $response_message = $print_response['success'] ? 'Printed successfully' : 'Unknown error';
        }
        
        if (isset($print_response['success']) && $print_response['success']) {
            $success_count++;
            $results[] = [
                'kitchen_id' => $kitchen_id,
                'success' => true,
                'message' => $response_message,
                'kitchen_name' => $print_response['kitchen_name'] ?? null,
                'printer_ip' => $print_response['printer_ip'] ?? null
            ];
        } else {
            $error_count++;
            $results[] = [
                'kitchen_id' => $kitchen_id,
                'success' => false,
                'message' => $response_message,
                'printer_ip' => $print_response['printer_ip'] ?? null
            ];
        }
    }
    
    // Clear buffer and return response
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        'success' => $error_count === 0,
        'message' => "Printed to $success_count kitchen(s), $error_count error(s)",
        'results' => $results,
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    error_log("POS Print Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit();
    
} catch (Error $e) {
    error_log("POS Print Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
    exit();
}

exit();
?>
