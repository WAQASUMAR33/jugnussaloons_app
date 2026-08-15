<?php

/**
 * Get Kitchen Orders API - Multi-Branch Support
 * Fetches orders and items for a specific kitchen and branch
 * 
 * GET/POST Parameters:
 * - kitchen_id (int, required) - Kitchen ID
 * - branch_id (int, required) - Branch ID
 * - status (string, optional) - Filter by status: Pending, Preparing, Ready, Completed
 * - terminal (int, optional) - Terminal number
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    exit();
}

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
    // Handle both GET and POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = $_GET;
    } else {
        $raw_input = file_get_contents('php://input');
        $input = json_decode($raw_input, true);
        
        if (!$input || !is_array($input)) {
            $input = $_POST;
        }
    }
    
    $kitchen_id = isset($input['kitchen_id']) ? intval($input['kitchen_id']) : 0;
    $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
    $status_filter = isset($input['status']) ? trim($input['status']) : '';
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    
    if (empty($kitchen_id) || $kitchen_id <= 0) {
        throw new Exception("Kitchen ID is required");
    }
    
    if (empty($branch_id) || $branch_id <= 0) {
        // Fallback to terminal if branch_id not provided
        $branch_id = $terminal;
    }
    
    // Get running orders for this kitchen and branch
    $sql = "SELECT DISTINCT
                o.order_id,
                o.order_type,
                o.order_status,
                o.table_id,
                o.hall_id,
                o.comments as order_comments,
                o.created_at as order_created_at,
                t.table_number,
                h.name as hall_name,
                kos.status as kitchen_status,
                kos.items_total,
                kos.items_completed,
                TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as minutes_running
            FROM orders o
            INNER JOIN kitchen_order_status kos ON o.order_id = kos.order_id
            LEFT JOIN tables t ON o.table_id = t.table_id
            LEFT JOIN halls h ON o.hall_id = h.hall_id
            WHERE kos.kitchen_id = ?
            AND o.branch_id = ?
            AND o.order_type != 'Customer Registration'
            AND o.order_status IN ('Running', 'Preparing')
            AND kos.status != 'Completed'";
    
    if (!empty($status_filter)) {
        $sql .= " AND kos.status = ?";
    }
    
    $sql .= " ORDER BY o.created_at DESC";
    
    $stmt = mysqli_prepare($connection, $sql);
    
    if (!empty($status_filter)) {
        mysqli_stmt_bind_param($stmt, "iis", $kitchen_id, $branch_id, $status_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $kitchen_id, $branch_id);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Check if order_items table exists (do this once, not in loop)
    $check_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
    $has_order_items_table = ($check_table && mysqli_num_rows($check_table) > 0);
    
    $orders = [];
    while ($order = mysqli_fetch_assoc($result)) {
        // Get items for this order, kitchen, and branch
        if ($has_order_items_table) {
            // Use order_items table (matches database schema)
            $items_sql = "SELECT 
                            oik.id,
                            oik.order_detail_id,
                            oik.dish_id,
                            oik.dish_name,
                            oik.quantity,
                            oik.price,
                            oik.status,
                            oik.notes,
                            oik.created_at,
                            c.name as category_name
                          FROM order_items_kitchen oik
                          LEFT JOIN order_items oi ON oik.order_detail_id = oi.item_id
                          LEFT JOIN dishes d ON oik.dish_id = d.dish_id
                          LEFT JOIN categories c ON d.category_id = c.category_id
                          WHERE oik.order_id = ? AND oik.kitchen_id = ? AND oik.branch_id = ?
                          ORDER BY oik.created_at ASC";
        } else {
            // Fallback to orderdetails table (for backward compatibility)
            $items_sql = "SELECT 
                            oik.id,
                            oik.order_detail_id,
                            oik.dish_id,
                            oik.dish_name,
                            oik.quantity,
                            oik.price,
                            oik.status,
                            oik.notes,
                            oik.created_at,
                            c.name as category_name
                          FROM order_items_kitchen oik
                          LEFT JOIN orderdetails od ON oik.order_detail_id = od.id
                          LEFT JOIN dishes d ON oik.dish_id = d.dish_id
                          LEFT JOIN categories c ON d.category_id = c.category_id
                          WHERE oik.order_id = ? AND oik.kitchen_id = ? AND oik.branch_id = ?
                          ORDER BY oik.created_at ASC";
        }
        
        $items_stmt = mysqli_prepare($connection, $items_sql);
        mysqli_stmt_bind_param($items_stmt, "iii", $order['order_id'], $kitchen_id, $branch_id);
        mysqli_stmt_execute($items_stmt);
        $items_result = mysqli_stmt_get_result($items_stmt);
        
        $items = [];
        while ($item = mysqli_fetch_assoc($items_result)) {
            $items[] = $item;
        }
        mysqli_stmt_close($items_stmt);
        
        $order['items'] = $items;
        $order['items_count'] = count($items);
        $orders[] = $order;
    }
    mysqli_stmt_close($stmt);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => true,
        "data" => $orders,
        "kitchen_id" => $kitchen_id,
        "branch_id" => $branch_id
    ]);
    
} catch (Exception $e) {
    error_log("Get Kitchen Orders Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
}

exit();
?>
