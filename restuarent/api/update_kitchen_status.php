<?php

/**
 * Update Kitchen Item Status API
 * Updates status of individual items in kitchen orders
 * 
 * POST Parameters (JSON):
 * - id (int, required) - order_items_kitchen.id
 * - status (string, required) - Pending, Preparing, Ready, Completed, Cancelled
 * - order_id (int, optional) - If provided, updates all items in order for this kitchen
 * - kitchen_id (int, optional) - Required if order_id is provided
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
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $status = isset($input['status']) ? trim($input['status']) : '';
    $order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
    $kitchen_id = isset($input['kitchen_id']) ? intval($input['kitchen_id']) : 0;
    
    $valid_statuses = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception("Invalid status. Must be one of: " . implode(', ', $valid_statuses));
    }
    
    mysqli_begin_transaction($connection);
    
    try {
        if ($id > 0) {
            // Update single item
            $update_sql = "UPDATE order_items_kitchen SET status = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($connection, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $status, $id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
            
            // Get order_id and kitchen_id from the item
            $get_sql = "SELECT order_id, kitchen_id FROM order_items_kitchen WHERE id = ?";
            $get_stmt = mysqli_prepare($connection, $get_sql);
            mysqli_stmt_bind_param($get_stmt, "i", $id);
            mysqli_stmt_execute($get_stmt);
            $result = mysqli_stmt_get_result($get_stmt);
            $item = mysqli_fetch_assoc($result);
            mysqli_stmt_close($get_stmt);
            
            $order_id = $item['order_id'];
            $kitchen_id = $item['kitchen_id'];
            
        } elseif ($order_id > 0 && $kitchen_id > 0) {
            // Update all items for this order and kitchen
            $update_sql = "UPDATE order_items_kitchen SET status = ?, updated_at = NOW() 
                          WHERE order_id = ? AND kitchen_id = ?";
            $update_stmt = mysqli_prepare($connection, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sii", $status, $order_id, $kitchen_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        } else {
            throw new Exception("Either 'id' or both 'order_id' and 'kitchen_id' are required");
        }
        
        // Update kitchen_order_status based on item completion
        $count_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
                      FROM order_items_kitchen 
                      WHERE order_id = ? AND kitchen_id = ?";
        $count_stmt = mysqli_prepare($connection, $count_sql);
        mysqli_stmt_bind_param($count_stmt, "ii", $order_id, $kitchen_id);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $counts = mysqli_fetch_assoc($count_result);
        mysqli_stmt_close($count_stmt);
        
        // Determine kitchen status
        $kitchen_status = 'Pending';
        if ($counts['completed'] == $counts['total'] && $counts['total'] > 0) {
            $kitchen_status = 'Completed';
        } elseif ($counts['completed'] > 0) {
            $kitchen_status = 'In Progress';
        }
        
        // Update kitchen_order_status
        $status_sql = "UPDATE kitchen_order_status 
                      SET status = ?, items_completed = ?, updated_at = NOW()
                      WHERE order_id = ? AND kitchen_id = ?";
        $status_stmt = mysqli_prepare($connection, $status_sql);
        mysqli_stmt_bind_param($status_stmt, "siii", $kitchen_status, $counts['completed'], $order_id, $kitchen_id);
        mysqli_stmt_execute($status_stmt);
        mysqli_stmt_close($status_stmt);
        
        mysqli_commit($connection);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "message" => "Status updated successfully",
            "data" => [
                "order_id" => $order_id,
                "kitchen_id" => $kitchen_id,
                "status" => $status,
                "kitchen_status" => $kitchen_status,
                "items_completed" => intval($counts['completed']),
                "items_total" => intval($counts['total'])
            ]
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Update Kitchen Item Status Error: " . $e->getMessage());
    
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

