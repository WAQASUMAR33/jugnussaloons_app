<?php
/**
 * Upload Order Details API
 * Handles creating and updating order details in orderdetails table
 * Supports both JSON and form data
 * Uses secure prepared statements
 */
require_once 'cors_headers.php';

// Disable error display
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
        echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $error['message']]);
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

// Check database connection
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

// Verify database connection is alive
if (!mysqli_ping($connection)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(['status' => 'error', 'message' => 'Database connection lost']);
    exit();
}

try {
    // Get input data - handle both JSON and form data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST; // Fallback to form data
    }
    
    // Fetching POST data with validation
    $id = isset($input["id"]) ? trim($input["id"]) : '';
    $userid = isset($input["userid"]) ? trim($input["userid"]) : '';
    $p_id = isset($input["p_id"]) ? trim($input["p_id"]) : '';
    $rate = isset($input["rate"]) ? floatval($input["rate"]) : 0.00;
    $qnty = isset($input["qnty"]) ? intval($input["qnty"]) : 0;
    $total = isset($input["total"]) ? floatval($input["total"]) : 0.00;
    
    // Validate required fields
    if (empty($userid) || empty($p_id) || $qnty <= 0 || $rate <= 0) {
        throw new Exception('Missing required fields: userid, p_id, rate, and qnty are required');
    }
    
    $current_date = date("Y-m-d H:i:s");
    
    // Insert or update logic
    if (empty($id)) {
        // Insert new record if no ID provided
        $sql = "INSERT INTO orderdetails (userid, p_id, rate, qnty, total, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "ssdids", $userid, $p_id, $rate, $qnty, $total, $current_date, $current_date);
        
        if (mysqli_stmt_execute($stmt)) {
            $insert_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt);
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            echo json_encode([
                'status' => 'success', 
                'message' => 'Order detail added successfully',
                'id' => $insert_id
            ]);
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('Error executing statement: ' . $error);
        }
    } else {
        // Update existing record if ID provided
        $sql = "UPDATE orderdetails 
                SET userid = ?, p_id = ?, rate = ?, qnty = ?, total = ?, updated_at = ? 
                WHERE id = ?";
        
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "ssdidsi", $userid, $p_id, $rate, $qnty, $total, $current_date, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $affected = mysqli_affected_rows($connection);
            mysqli_stmt_close($stmt);
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            if ($affected > 0) {
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Order detail updated successfully'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'No order detail found with the provided ID'
                ]);
            }
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('Error executing statement: ' . $error);
        }
    }
    
} catch (Exception $e) {
    error_log("Upload Order Details Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Upload Order Details Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
}

exit();
?>
