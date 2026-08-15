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

require_once 'cors_headers.php';

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

// Check connection
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
    // Get input data
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    // Validate required parameters
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $status = isset($input['status']) ? trim($input['status']) : '';
    $order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
    $kitchen_id = isset($input['kitchen_id']) ? intval($input['kitchen_id']) : 0;
    
    // Validate status
    $valid_statuses = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (empty($status) || !in_array($status, $valid_statuses)) {
        throw new Exception("Invalid status. Must be one of: " . implode(', ', $valid_statuses));
    }
    
    // Validate that either id or both order_id and kitchen_id are provided
    if ($id <= 0 && ($order_id <= 0 || $kitchen_id <= 0)) {
        throw new Exception("Either 'id' or both 'order_id' and 'kitchen_id' are required");
    }
    
    // Start transaction
    mysqli_begin_transaction($connection);
    
    try {
        if ($id > 0) {
            // Update single item by id
            $update_sql = "UPDATE order_items_kitchen SET status = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($connection, $update_sql);
            if (!$update_stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($update_stmt, "si", $status, $id);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                $error = mysqli_error($connection);
                mysqli_stmt_close($update_stmt);
                throw new Exception("Error updating item: " . ($error ?: "Unknown error"));
            }
            
            $affected_rows = mysqli_stmt_affected_rows($update_stmt);
            mysqli_stmt_close($update_stmt);
            
            if ($affected_rows === 0) {
                throw new Exception("No item found with id: " . $id);
            }
            
            // Get order_id and kitchen_id from the item
            $get_sql = "SELECT order_id, kitchen_id FROM order_items_kitchen WHERE id = ?";
            $get_stmt = mysqli_prepare($connection, $get_sql);
            if (!$get_stmt) {
                throw new Exception("Error preparing get statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($get_stmt, "i", $id);
            mysqli_stmt_execute($get_stmt);
            $result = mysqli_stmt_get_result($get_stmt);
            $item = mysqli_fetch_assoc($result);
            mysqli_stmt_close($get_stmt);
            
            if (!$item) {
                throw new Exception("Item not found after update");
            }
            
            $order_id = intval($item['order_id']);
            $kitchen_id = intval($item['kitchen_id']);
            
        } elseif ($order_id > 0 && $kitchen_id > 0) {
            // Update all items for this order and kitchen
            $update_sql = "UPDATE order_items_kitchen SET status = ?, updated_at = NOW() 
                          WHERE order_id = ? AND kitchen_id = ?";
            $update_stmt = mysqli_prepare($connection, $update_sql);
            if (!$update_stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($update_stmt, "sii", $status, $order_id, $kitchen_id);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                $error = mysqli_error($connection);
                mysqli_stmt_close($update_stmt);
                throw new Exception("Error updating items: " . ($error ?: "Unknown error"));
            }
            
            $affected_rows = mysqli_stmt_affected_rows($update_stmt);
            mysqli_stmt_close($update_stmt);
            
            if ($affected_rows === 0) {
                throw new Exception("No items found for order_id: " . $order_id . " and kitchen_id: " . $kitchen_id);
            }
        }
        
        // Update kitchen_order_status based on item completion
        $count_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
                      FROM order_items_kitchen 
                      WHERE order_id = ? AND kitchen_id = ?";
        $count_stmt = mysqli_prepare($connection, $count_sql);
        if (!$count_stmt) {
            throw new Exception("Error preparing count statement: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param($count_stmt, "ii", $order_id, $kitchen_id);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $counts = mysqli_fetch_assoc($count_result);
        mysqli_stmt_close($count_stmt);
        
        if (!$counts) {
            throw new Exception("Error getting item counts");
        }
        
        // Determine kitchen status
        $kitchen_status = 'Pending';
        $total = intval($counts['total'] ?? 0);
        $completed = intval($counts['completed'] ?? 0);
        
        if ($completed == $total && $total > 0) {
            $kitchen_status = 'Completed';
        } elseif ($completed > 0) {
            $kitchen_status = 'In Progress';
        }
        
        // Update or insert kitchen_order_status
        $check_sql = "SELECT id FROM kitchen_order_status WHERE order_id = ? AND kitchen_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $order_id, $kitchen_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $exists = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);
        
        if ($exists) {
            // Update existing record
            $status_sql = "UPDATE kitchen_order_status 
                          SET status = ?, items_completed = ?, updated_at = NOW()
                          WHERE order_id = ? AND kitchen_id = ?";
            $status_stmt = mysqli_prepare($connection, $status_sql);
            if (!$status_stmt) {
                throw new Exception("Error preparing status update: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($status_stmt, "siii", $kitchen_status, $completed, $order_id, $kitchen_id);
            mysqli_stmt_execute($status_stmt);
            mysqli_stmt_close($status_stmt);
        } else {
            // Insert new record
            $status_sql = "INSERT INTO kitchen_order_status 
                          (order_id, kitchen_id, status, items_total, items_completed, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $status_stmt = mysqli_prepare($connection, $status_sql);
            if (!$status_stmt) {
                throw new Exception("Error preparing status insert: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($status_stmt, "iisii", $order_id, $kitchen_id, $kitchen_status, $total, $completed);
            mysqli_stmt_execute($status_stmt);
            mysqli_stmt_close($status_stmt);
        }
        
        // Commit transaction
        mysqli_commit($connection);
        
        // Clear buffer and output JSON
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
                "items_completed" => $completed,
                "items_total" => $total
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
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
} catch (Error $e) {
    error_log("Update Kitchen Item Status Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Fatal error: " . $e->getMessage()
    ]);
    exit();
}

exit();
?>

