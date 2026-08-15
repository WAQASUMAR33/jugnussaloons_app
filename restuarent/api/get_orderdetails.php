<?php
/**
 * Get Order Details API
 * Returns order items/details for a specific order
 * Supports both JSON and form data
 * Joins with dishes table only (products table doesn't exist)
 */
require_once 'cors_headers.php';
// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
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
    echo json_encode(['status' => 'error', 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit();
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(['status' => 'error', 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit();
}

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get input data - handle both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST; // Fallback to form data
}

// Get the orderid and order_id parameters
$orderid = $input['orderid'] ?? ($_POST['orderid'] ?? '');
$order_id = $input['order_id'] ?? ($_POST['order_id'] ?? '');

// Extract numeric ID if orderid is like "ORD-123"
if (!empty($orderid) && preg_match('/ORD-?(\d+)/i', $orderid, $matches)) {
    $order_id = $matches[1];
}

if (empty($orderid) && empty($order_id)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(['status' => 'error', 'message' => 'Order ID is required']);
    exit();
}

// Check connection
if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

try {
    // Prepare order_id - extract from orderid string if needed
    if (empty($order_id) && !empty($orderid)) {
        if (preg_match('/ORD-?(\d+)/i', $orderid, $matches)) {
            $order_id = intval($matches[1]);
        }
    }
    
    // Check if order_items table exists, if not use orderdetails
    $check_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
    $has_order_items_table = ($check_table && mysqli_num_rows($check_table) > 0);
    
    $orderArray = array();
    
    if ($has_order_items_table && !empty($order_id)) {
        // Use order_items table - matching actual database structure
        $sql = "SELECT order_items.*, 
                COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                COALESCE(dishes.name, 'Unknown Dish') as title,
                COALESCE(dishes.description, '') as description
                FROM order_items 
                LEFT JOIN dishes ON dishes.dish_id = order_items.dish_id 
                WHERE order_items.order_id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $order_id);
    } else {
        // Fallback to orderdetails table - only join with dishes (products table doesn't exist)
        $orderid_str = !empty($orderid) ? $orderid : ('ORD-' . $order_id);
        $sql = "SELECT orderdetails.*, 
                COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                COALESCE(dishes.name, 'Unknown Dish') as title,
                COALESCE(dishes.description, '') as description
                FROM orderdetails 
                LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                WHERE orderdetails.orderid = ?";
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "s", $orderid_str);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $orderArray[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
    
    // Fetch order header information with totals
    $order_header_sql = "SELECT 
                            order_id, 
                            order_type, 
                            order_status, 
                            g_total_amount, 
                            service_charge, 
                            discount_amount, 
                            net_total_amount,
                            table_id,
                            hall_id,
                            comments,
                            terminal,
                            branch_id,
                            created_at,
                            updated_at
                         FROM orders 
                         WHERE order_id = ?";
    $order_header_stmt = mysqli_prepare($connection, $order_header_sql);
    
    if ($order_header_stmt) {
        mysqli_stmt_bind_param($order_header_stmt, "i", $order_id);
        mysqli_stmt_execute($order_header_stmt);
        $order_header_result = mysqli_stmt_get_result($order_header_stmt);
        $order_header = mysqli_fetch_assoc($order_header_result);
        mysqli_stmt_close($order_header_stmt);
    } else {
        $order_header = null;
    }
    
    // Prepare response with order header and items
    $response = [
        'order' => $order_header,
        'items' => $orderArray
    ];
    
    // If order header not found, return items only (backward compatibility)
    if (!$order_header) {
        $response = $orderArray;
    }
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Return the JSON-encoded result
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    error_log("Get Order Details Error: " . $e->getMessage());
    
    // Clear buffer and return error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(['status' => 'error', 'message' => 'Error in Selecting: ' . $e->getMessage()]);
} catch (Error $e) {
    // Log fatal error
    error_log("Get Order Details Fatal Error: " . $e->getMessage());
    
    // Clear buffer and return error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(['status' => 'error', 'message' => 'Fatal error: ' . $e->getMessage()]);
}

// Don't close connection here - let PHP handle it automatically
exit();
?>
