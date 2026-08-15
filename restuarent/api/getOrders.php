<?php

/**
 * Get Orders API - Multi-Branch Support
 * Returns list of all orders with branch filtering
 * 
 * Branch-Admin: Returns only their branch's orders (requires branch_id)
 * Super-Admin: Returns all orders with branch info (no branch_id or branch_id = null)
 * 
 * POST Parameters:
 * - terminal (int, optional) - Terminal number (default: 1)
 * - branch_id (int/string, optional) - Branch ID (if null/empty, returns all orders for super-admin)
 * - status (string, optional) - Filter by status (e.g., "Running", "Complete", "all")
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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

ob_start();

require_once 'cors_headers.php';

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
}

if (!isset($connection) || !$connection || !mysqli_ping($connection)) {
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
    // Get JSON input
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    // Handle both POST body and GET parameters
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    // Handle GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = $_GET;
    }
    
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    $branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : null;
    $status = isset($input['status']) ? trim($input['status']) : null;
    
    // Convert branch_id to integer or null
    // Handle null, empty string, 'null', 'undefined', etc.
    if ($branch_id_input === '' || $branch_id_input === 'null' || $branch_id_input === 'undefined' || $branch_id_input === null) {
        $branch_id = null;
    } else {
        $branch_id = intval($branch_id_input);
        if ($branch_id <= 0) {
            $branch_id = null;
        }
    }
    
    // Build SQL query based on branch_id and status
    // Note: orders table doesn't have c_name column - customer info comes from customer_id
    // Join with bills table to get payment_status
    $sql = "SELECT 
                o.order_id,
                o.order_type,
                o.order_status,
                o.table_id,
                o.hall_id,
                o.customer_id,
                o.g_total_amount,
                o.discount_amount,
                o.service_charge,
                o.net_total_amount,
                o.payment_mode,
                o.order_taker_id,
                o.created_at,
                o.terminal,
                o.branch_id,
                o.comments,
                t.table_number,
                h.name AS hall_name_from_table,
                b.branch_name,
                bill.payment_status,
                bill.payment_method,
                bill.bill_id
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.table_id AND o.branch_id = t.branch_id AND o.terminal = t.terminal
            LEFT JOIN halls h ON o.hall_id = h.hall_id
            LEFT JOIN branches b ON o.branch_id = b.branch_id
            LEFT JOIN bills bill ON o.order_id = bill.order_id
            WHERE 1=1
            AND o.order_type != 'Customer Registration'
            AND o.order_status != 'Customer Created'";
    
    $params = [];
    $types = "";
    
    // Add branch filter if provided
    if ($branch_id !== null) {
        $sql .= " AND o.branch_id = ?";
        $params[] = $branch_id;
        $types .= "i";
    }
    
    // Add terminal filter
    $sql .= " AND o.terminal = ?";
    $params[] = $terminal;
    $types .= "i";
    
    // Add status filter if provided (only order_status exists in table)
    if ($status && $status !== 'all' && !empty($status)) {
        $sql .= " AND o.order_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Add ORDER BY clause
    if ($branch_id !== null) {
        $sql .= " ORDER BY o.created_at DESC, o.order_id DESC";
    } else {
        $sql .= " ORDER BY o.branch_id ASC, o.created_at DESC, o.order_id DESC";
    }
    
    // Prepare and execute statement
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    // Bind parameters
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Error executing query: " . mysqli_error($connection));
    }
    
    $orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Normalize branch_name
        $branch_name = $row['branch_name'] ?? null;
        if (!$branch_name && $row['branch_id']) {
            $branch_name = 'Branch ' . $row['branch_id'];
        }
        
        // Normalize order status (only order_status column exists)
        $order_status = $row['order_status'] ?? 'Pending';
        
        // Generate order_number (doesn't exist in DB, generate from order_id)
        $order_number = $row['order_id'] ? 'ORD-' . $row['order_id'] : '';
        
        // Normalize hall_name
        $hall_name = $row['hall_name_from_table'] ?? null;
        
        // Normalize customer_name (c_name column doesn't exist - use customer_id instead)
        $customer_name = null; // Customer name would come from customers table via customer_id if needed
        
        // Get payment_status from bills table (defaults to 'Unpaid' if no bill exists)
        $payment_status = $row['payment_status'] ?? 'Unpaid';
        
        $orders[] = [
            'order_id' => intval($row['order_id']),
            'id' => intval($row['order_id']), // Alias for frontend compatibility
            'orderid' => $order_number,
            'order_number' => $order_number,
            'order_type' => $row['order_type'] ?? 'Dine In',
            'order_status' => $order_status,
            'status' => strtolower($order_status),
            'table_id' => $row['table_id'] ? intval($row['table_id']) : null,
            'tableid' => $row['table_id'], // Alias
            'table_number' => $row['table_number'] ?? null,
            'hall_id' => $row['hall_id'] ?? null,
            'hall_name' => $hall_name,
            'customer_name' => $customer_name,
            'g_total_amount' => floatval($row['g_total_amount'] ?? 0),
            'total' => floatval($row['g_total_amount'] ?? 0), // Alias
            'subtotal' => floatval($row['g_total_amount'] ?? 0), // Same as total for now
            'net_total_amount' => floatval($row['net_total_amount'] ?? 0),
            'netTotal' => floatval($row['net_total_amount'] ?? 0), // Alias
            'discount_amount' => floatval($row['discount_amount'] ?? 0),
            'discount' => floatval($row['discount_amount'] ?? 0), // Alias
            'service_charge' => floatval($row['service_charge'] ?? 0),
            'payment_mode' => $row['payment_mode'] ?? 'Cash',
            'payment_method' => $row['payment_method'] ?? $row['payment_mode'] ?? 'Cash',
            'payment_status' => $payment_status,
            'is_paid' => ($payment_status === 'Paid'),
            'is_credit' => (strtolower(trim($row['payment_method'] ?? '')) === 'credit' || strtolower(trim($row['payment_mode'] ?? '')) === 'credit'),
            'bill_id' => $row['bill_id'] ? intval($row['bill_id']) : null,
            'order_taker_id' => $row['order_taker_id'] ? intval($row['order_taker_id']) : null,
            'created_at' => $row['created_at'] ?? null,
            'date' => $row['created_at'] ?? null, // Alias
            'terminal' => intval($row['terminal']),
            'branch_id' => $row['branch_id'] ? intval($row['branch_id']) : null,
            'branch_name' => $branch_name
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Always ensure orders array exists
    if (!isset($orders)) {
        $orders = [];
    }
    
    // Clear buffer before output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Return success response with orders array
    echo json_encode([
        'success' => true,
        'data' => $orders,
        'count' => count($orders)
    ]);
    exit();
    
} catch (Exception $e) {
    error_log("Get Orders Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch orders',
        'message' => $e->getMessage()
    ]);
    exit();
} catch (Error $e) {
    error_log("Get Orders Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error',
        'message' => $e->getMessage()
    ]);
    exit();
}

exit();
?>
