<?php
/**
 * Table Management API
 * Handles CRUD operations for tables
 * 
 * CREATE: POST with { hall_id, table_number, capacity, status, terminal } (no table_id)
 * UPDATE: POST with { table_id, hall_id, table_number, capacity, status, terminal }
 * DELETE: DELETE with { table_id }
 * 
 * Database Schema (tables table):
 * - table_id (int, AUTO_INCREMENT, PRIMARY KEY)
 * - hall_id (int, NOT NULL, FOREIGN KEY to halls)
 * - table_number (varchar)
 * - capacity (int)
 * - status (varchar) - 'Available', 'Running', 'Unavailable', 'Bill Generated', 'Cancelled'
 * - terminal (int)
 * - created_at (timestamp)
 * - updated_at (timestamp)
 */
require_once 'cors_headers.php';

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

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    exit();
}

// Include database config
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
    // Get JSON input
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    // If JSON decode failed, try POST data
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // ============================================
    // DELETE TABLE
    // ============================================
    if ($method === 'DELETE') {
        $table_id = isset($input['table_id']) ? intval($input['table_id']) : 0;
        
        if (empty($table_id) || $table_id <= 0) {
            throw new Exception("Table ID is required for deletion");
        }
        
        // Check if table exists
        $check_sql = "SELECT table_id FROM tables WHERE table_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        
        if (!$check_stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($check_stmt, "i", $table_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $existing_table = mysqli_fetch_assoc($result);
        mysqli_stmt_close($check_stmt);
        
        if (!$existing_table) {
            throw new Exception("Table not found");
        }
        
        // Delete table
        $delete_sql = "DELETE FROM tables WHERE table_id = ?";
        $delete_stmt = mysqli_prepare($connection, $delete_sql);
        
        if (!$delete_stmt) {
            throw new Exception("Error preparing delete statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($delete_stmt, "i", $table_id);
        
        if (!mysqli_stmt_execute($delete_stmt)) {
            $error_msg = mysqli_error($connection);
            mysqli_stmt_close($delete_stmt);
            throw new Exception("Error deleting table: " . $error_msg);
        }
        
        $affected_rows = mysqli_stmt_affected_rows($delete_stmt);
        mysqli_stmt_close($delete_stmt);
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        if ($affected_rows > 0) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Table deleted successfully"
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Table not found"
            ]);
        }
        exit();
    }
    
    // ============================================
    // CREATE OR UPDATE TABLE
    // ============================================
    if ($method === 'POST' || $method === 'PUT') {
        // Extract parameters
        $table_id = isset($input['table_id']) ? intval($input['table_id']) : 0;
        $hall_id = isset($input['hall_id']) ? intval($input['hall_id']) : 0;
        $table_number = isset($input['table_number']) ? trim($input['table_number']) : '';
        $capacity = isset($input['capacity']) ? intval($input['capacity']) : 0;
        $status = isset($input['status']) ? trim($input['status']) : 'Available';
        $terminal = isset($input['terminal']) ? intval($input['terminal']) : 0;
        $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
        $action = isset($input['action']) ? trim($input['action']) : '';
        
        // Validate required fields
        if (empty($branch_id) || $branch_id <= 0) {
            throw new Exception("branch_id is required");
        }
        
        if (empty($terminal) || $terminal <= 0) {
            throw new Exception("terminal is required");
        }
        
        // If action is 'update', validate table_id and status update only
        if ($action === 'update' && !empty($table_id)) {
            // Update table status only
            $update_sql = "UPDATE tables 
                            SET status = ?, updated_at = NOW()
                            WHERE table_id = ? AND branch_id = ? AND terminal = ?";
            
            $update_stmt = mysqli_prepare($connection, $update_sql);
            
            if (!$update_stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($connection));
            }
            
            // Normalize status
            $status_normalized = ucwords(strtolower($status));
            if ($status_normalized === 'Bill Generated') {
                $status_normalized = 'Bill Generated';
            }
            $valid_statuses = ['Available', 'Running', 'Occupied', 'Unavailable', 'Bill Generated', 'Cancelled'];
            if (!in_array($status_normalized, $valid_statuses)) {
                $status_normalized = 'Available';
            }
            
            mysqli_stmt_bind_param($update_stmt, "siii",
                $status_normalized,
                $table_id,
                $branch_id,
                $terminal
            );
            
            if (!mysqli_stmt_execute($update_stmt)) {
                $error_msg = mysqli_error($connection);
                mysqli_stmt_close($update_stmt);
                throw new Exception("Error updating table status: " . $error_msg);
            }
            
            $affected_rows = mysqli_stmt_affected_rows($update_stmt);
            mysqli_stmt_close($update_stmt);
            
            if ($affected_rows <= 0) {
                throw new Exception("Table not found or does not belong to this branch and terminal");
            }
            
            // Get updated table with branch and hall info
            $get_sql = "SELECT t.*, 
                       h.name AS hall_name,
                       COALESCE(b.branch_name, CONCAT('Branch ', t.branch_id)) AS branch_name
                       FROM tables t 
                       LEFT JOIN halls h ON t.hall_id = h.hall_id 
                       LEFT JOIN branches b ON t.branch_id = b.branch_id
                       WHERE t.table_id = ? AND t.branch_id = ? AND t.terminal = ?";
            $get_stmt = mysqli_prepare($connection, $get_sql);
            mysqli_stmt_bind_param($get_stmt, "iii", $table_id, $branch_id, $terminal);
            mysqli_stmt_execute($get_stmt);
            $result = mysqli_stmt_get_result($get_stmt);
            $updated_table = mysqli_fetch_assoc($result);
            mysqli_stmt_close($get_stmt);
            
            // Normalize branch_name
            if (!isset($updated_table['branch_name']) || empty($updated_table['branch_name'])) {
                $updated_table['branch_name'] = $branch_id ? 'Branch ' . $branch_id : 'No Branch';
            }
            
            // Clear buffer and output JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Table status updated successfully",
                "data" => $updated_table
            ]);
            exit();
        }
        
        if (empty($hall_id) || $hall_id <= 0) {
            throw new Exception("Hall ID is required");
        }
        
        if (empty($table_number)) {
            throw new Exception("Table number is required");
        }
        
        // Validate status value
        $valid_statuses = ['Available', 'Running', 'Unavailable', 'Bill Generated', 'Cancelled', 
                          'available', 'running', 'unavailable', 'bill generated', 'cancelled'];
        
        // Normalize status to proper case
        $status_normalized = ucwords(strtolower($status));
        if ($status_normalized === 'Bill Generated') {
            $status_normalized = 'Bill Generated';
        }
        
        if (!in_array($status, $valid_statuses) && !in_array($status_normalized, ['Available', 'Running', 'Unavailable', 'Bill Generated', 'Cancelled'])) {
            // Default to Available if invalid
            $status_normalized = 'Available';
        } else {
            $status = $status_normalized;
        }
        
        // Check if hall exists and validate it belongs to the same branch
        $check_hall_sql = "SELECT hall_id, branch_id FROM halls WHERE hall_id = ?";
        $check_hall_stmt = mysqli_prepare($connection, $check_hall_sql);
        
        if ($check_hall_stmt) {
            mysqli_stmt_bind_param($check_hall_stmt, "i", $hall_id);
            mysqli_stmt_execute($check_hall_stmt);
            $result = mysqli_stmt_get_result($check_hall_stmt);
            $hall_data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($check_hall_stmt);
            
            if (!$hall_data) {
                throw new Exception("Hall not found with ID: " . $hall_id);
            }
            
            // Validate hall belongs to the same branch as branch_id
            $hall_branch_id = intval($hall_data['branch_id']);
            if ($hall_branch_id !== $branch_id) {
                throw new Exception("Hall does not belong to branch " . $branch_id . ". Hall belongs to branch " . $hall_branch_id);
            }
        }
        
        // Check if this is an update or create
        $is_update = !empty($table_id) && $table_id > 0;
        
        if ($is_update) {
            // UPDATE EXISTING TABLE
            // Check if table exists
            $check_sql = "SELECT * FROM tables WHERE table_id = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            
            if (!$check_stmt) {
                throw new Exception("Error preparing check statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($check_stmt, "i", $table_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $existing_table = mysqli_fetch_assoc($result);
            mysqli_stmt_close($check_stmt);
            
            if (!$existing_table) {
                throw new Exception("Table not found with ID: " . $table_id);
            }
            
            // If branch_id not provided in update, use existing branch_id (prevent branch-admin from changing it)
            $existing_branch_id = intval($existing_table['branch_id']);
            if (empty($branch_id) || $branch_id <= 0) {
                $branch_id = $existing_branch_id;
            }
            
            // Validate hall belongs to the same branch as branch_id (if hall_id is being updated)
            if ($hall_id > 0) {
                $check_hall_sql = "SELECT hall_id, branch_id FROM halls WHERE hall_id = ?";
                $check_hall_stmt = mysqli_prepare($connection, $check_hall_sql);
                
                if ($check_hall_stmt) {
                    mysqli_stmt_bind_param($check_hall_stmt, "i", $hall_id);
                    mysqli_stmt_execute($check_hall_stmt);
                    $result = mysqli_stmt_get_result($check_hall_stmt);
                    $hall_data = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($check_hall_stmt);
                    
                    if (!$hall_data) {
                        throw new Exception("Hall not found with ID: " . $hall_id);
                    }
                    
                    // Validate hall belongs to the same branch as branch_id
                    $hall_branch_id = intval($hall_data['branch_id']);
                    if ($hall_branch_id !== $branch_id) {
                        throw new Exception("Hall does not belong to branch " . $branch_id . ". Hall belongs to branch " . $hall_branch_id);
                    }
                }
            }
            
            // Check for duplicate table number in same hall and branch (excluding current table)
            $duplicate_sql = "SELECT table_id FROM tables WHERE hall_id = ? AND table_number = ? AND branch_id = ? AND terminal = ? AND table_id != ?";
            $duplicate_stmt = mysqli_prepare($connection, $duplicate_sql);
            
            if ($duplicate_stmt) {
                mysqli_stmt_bind_param($duplicate_stmt, "isiii", $hall_id, $table_number, $branch_id, $terminal, $table_id);
                mysqli_stmt_execute($duplicate_stmt);
                $result = mysqli_stmt_get_result($duplicate_stmt);
                $duplicate = mysqli_fetch_assoc($result);
                mysqli_stmt_close($duplicate_stmt);
                
                if ($duplicate) {
                    throw new Exception("Table number '{$table_number}' already exists in this hall");
                }
            }
            
            // Validate table belongs to branch_id and terminal
            $validate_sql = "SELECT table_id FROM tables WHERE table_id = ? AND branch_id = ? AND terminal = ?";
            $validate_stmt = mysqli_prepare($connection, $validate_sql);
            if ($validate_stmt) {
                mysqli_stmt_bind_param($validate_stmt, "iii", $table_id, $branch_id, $terminal);
                mysqli_stmt_execute($validate_stmt);
                $validate_result = mysqli_stmt_get_result($validate_stmt);
                if (mysqli_num_rows($validate_result) == 0) {
                    mysqli_stmt_close($validate_stmt);
                    throw new Exception("Table not found or does not belong to this branch and terminal");
                }
                mysqli_stmt_close($validate_stmt);
            }
            
            // Update table
            // Note: updated_at has ON UPDATE CURRENT_TIMESTAMP, so it will auto-update
            $update_sql = "UPDATE tables SET 
                            hall_id = ?,
                            table_number = ?,
                            capacity = ?,
                            status = ?,
                            branch_id = ?,
                            terminal = ?
                            WHERE table_id = ? AND terminal = ?";
            
            $update_stmt = mysqli_prepare($connection, $update_sql);
            
            if (!$update_stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($update_stmt, "isisiiii",
                $hall_id,
                $table_number,
                $capacity,
                $status,
                $branch_id,
                $terminal,
                $table_id,
                $terminal
            );
            
            if (!mysqli_stmt_execute($update_stmt)) {
                $error_msg = mysqli_error($connection);
                mysqli_stmt_close($update_stmt);
                throw new Exception("Error updating table: " . $error_msg);
            }
            
            mysqli_stmt_close($update_stmt);
            
            // Get updated table with branch and hall info
            $get_sql = "SELECT t.*, 
                       h.name AS hall_name,
                       COALESCE(b.branch_name, CONCAT('Branch ', t.branch_id)) AS branch_name
                       FROM tables t 
                       LEFT JOIN halls h ON t.hall_id = h.hall_id 
                       LEFT JOIN branches b ON t.branch_id = b.branch_id
                       WHERE t.table_id = ? AND t.branch_id = ? AND t.terminal = ?";
            $get_stmt = mysqli_prepare($connection, $get_sql);
            mysqli_stmt_bind_param($get_stmt, "iii", $table_id, $branch_id, $terminal);
            mysqli_stmt_execute($get_stmt);
            $result = mysqli_stmt_get_result($get_stmt);
            $updated_table = mysqli_fetch_assoc($result);
            mysqli_stmt_close($get_stmt);
            
            // Normalize branch_name
            if (!isset($updated_table['branch_name']) || empty($updated_table['branch_name'])) {
                $updated_table['branch_name'] = $branch_id ? 'Branch ' . $branch_id : 'No Branch';
            }
            
            // Clear buffer and output JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Table updated successfully",
                "data" => $updated_table
            ]);
            
        } else {
            // CREATE NEW TABLE
            // Check for duplicate table number in same hall and branch
            $duplicate_sql = "SELECT table_id FROM tables WHERE hall_id = ? AND table_number = ? AND branch_id = ? AND terminal = ?";
            $duplicate_stmt = mysqli_prepare($connection, $duplicate_sql);
            
            if ($duplicate_stmt) {
                mysqli_stmt_bind_param($duplicate_stmt, "isii", $hall_id, $table_number, $branch_id, $terminal);
                mysqli_stmt_execute($duplicate_stmt);
                $result = mysqli_stmt_get_result($duplicate_stmt);
                $duplicate = mysqli_fetch_assoc($result);
                mysqli_stmt_close($duplicate_stmt);
                
                if ($duplicate) {
                    throw new Exception("Table number '{$table_number}' already exists in this hall");
                }
            }
            
            // Check if halls table has branch_id field, if not we'll just insert without it
            // Insert new table with branch_id
            $insert_sql = "INSERT INTO tables (
                            hall_id,
                            table_number,
                            capacity,
                            status,
                            terminal,
                            branch_id
                          ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = mysqli_prepare($connection, $insert_sql);
            
            if (!$insert_stmt) {
                throw new Exception("Error preparing insert statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($insert_stmt, "isisii",
                $hall_id,
                $table_number,
                $capacity,
                $status,
                $terminal,
                $branch_id
            );
            
            if (!mysqli_stmt_execute($insert_stmt)) {
                $error_msg = mysqli_error($connection);
                mysqli_stmt_close($insert_stmt);
                throw new Exception("Error creating table: " . $error_msg);
            }
            
            $new_table_id = mysqli_insert_id($connection);
            mysqli_stmt_close($insert_stmt);
            
            // Get created table with branch and hall info
            $get_sql = "SELECT t.*, 
                       h.name AS hall_name,
                       COALESCE(b.branch_name, CONCAT('Branch ', t.branch_id)) AS branch_name
                       FROM tables t 
                       LEFT JOIN halls h ON t.hall_id = h.hall_id 
                       LEFT JOIN branches b ON t.branch_id = b.branch_id
                       WHERE t.table_id = ? AND t.branch_id = ? AND t.terminal = ?";
            $get_stmt = mysqli_prepare($connection, $get_sql);
            mysqli_stmt_bind_param($get_stmt, "iii", $new_table_id, $branch_id, $terminal);
            mysqli_stmt_execute($get_stmt);
            $result = mysqli_stmt_get_result($get_stmt);
            $created_table = mysqli_fetch_assoc($result);
            mysqli_stmt_close($get_stmt);
            
            // Normalize branch_name
            if (!isset($created_table['branch_name']) || empty($created_table['branch_name'])) {
                $created_table['branch_name'] = $branch_id ? 'Branch ' . $branch_id : 'No Branch';
            }
            
            // Clear buffer and output JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Table created successfully",
                "data" => $created_table
            ]);
        }
        
    } else {
        // Invalid method
        throw new Exception("Invalid request method. Use POST, PUT, or DELETE.");
    }
    
} catch (Exception $e) {
    error_log("Table Management Error: " . $e->getMessage());
    error_log("Table Management Error Trace: " . $e->getTraceAsString());
    
    // Clear buffer and return error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
    
} catch (Error $e) {
    error_log("Table Management Fatal Error: " . $e->getMessage());
    error_log("Table Management Fatal Error Trace: " . $e->getTraceAsString());
    
    // Clear buffer and return error
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

