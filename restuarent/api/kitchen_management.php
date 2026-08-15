<?php

/**
 * Kitchen Management API
 * CRUD operations for kitchens
 * Supports multi-branch kitchen management
 * 
 * POST Parameters (JSON):
 * - action (string, required) - 'get', 'create', 'update'
 * - kitchen_id (int, optional) - Required for update/delete
 * - title (string, required for create/update) - Kitchen name
 * - code (string, required for create/update) - Kitchen code (e.g., K1, K2)
 * - printer (string, optional) - Printer name
 * - terminal (int, required) - Terminal number
 * - branch_id (int, required) - Branch ID
 * 
 * DELETE Parameters:
 * - kitchen_id (int, required)
 * - terminal (int, required)
 * - branch_id (int, required)
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
    $method = $_SERVER['REQUEST_METHOD'];
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    // Handle both POST body and GET parameters
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    // Also check GET parameters for GET requests
    if ($method === 'GET' && empty($input)) {
        $input = $_GET;
    }
    
    $action = isset($input['action']) ? trim($input['action']) : '';
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : (isset($_GET['terminal']) ? intval($_GET['terminal']) : 1);
    $branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : (isset($_GET['branch_id']) ? $_GET['branch_id'] : null);
    
    // Allow null branch_id for super-admin (return all kitchens)
    // If branch_id is explicitly set to 0 or empty string, treat as null
    $branch_id = null;
    if ($branch_id_input !== null && $branch_id_input !== '' && $branch_id_input !== '0') {
        $branch_id = intval($branch_id_input);
        if ($branch_id <= 0) {
            $branch_id = null;
        }
    }
    
    // Handle DELETE method
    if ($method === 'DELETE') {
        $kitchen_id = isset($input['kitchen_id']) ? intval($input['kitchen_id']) : 0;
        
        if (empty($kitchen_id) || $kitchen_id <= 0) {
            throw new Exception("Kitchen ID is required for deletion");
        }
        
        // Check if kitchen is assigned to any categories
        $check_sql = "SELECT COUNT(*) as count FROM categories WHERE kitchen_id = ? AND terminal = ? AND branch_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "iii", $kitchen_id, $terminal, $branch_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_data = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);
        
        if ($check_data['count'] > 0) {
            throw new Exception("Cannot delete kitchen. It is assigned to " . $check_data['count'] . " categor" . ($check_data['count'] > 1 ? 'ies' : 'y') . ". Please reassign categories first.");
        }
        
        // Delete kitchen
        $delete_sql = "DELETE FROM kitchens WHERE kitchen_id = ? AND terminal = ? AND branch_id = ?";
        $delete_stmt = mysqli_prepare($connection, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "iii", $kitchen_id, $terminal, $branch_id);
        mysqli_stmt_execute($delete_stmt);
        $affected = mysqli_affected_rows($connection);
        mysqli_stmt_close($delete_stmt);
        
        if ($affected > 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            echo json_encode([
                "success" => true,
                "message" => "Kitchen deleted successfully",
                "data" => ["kitchen_id" => $kitchen_id]
            ]);
        } else {
            throw new Exception("Kitchen not found or already deleted");
        }
        exit();
    }
    
    // Handle GET method or POST with action='get'
    if ($method === 'GET' || $action === 'get' || ($method === 'POST' && empty($action))) {
        // IMPORTANT: Handle null/empty branch_id vs valid branch_id
        // When branch_id is null/empty: Return ALL kitchens (filter only by terminal - for super-admin)
        // When branch_id has value: Return only that branch's kitchens (filter by branch_id AND terminal)
        
        if ($branch_id === null) {
            // Get kitchens for ALL branches (super-admin) - only filter by terminal
            $sql = "SELECT 
                        kitchen_id,
                        title,
                        code,
                        printer,
                        branch_id,
                        terminal
                    FROM kitchens
                    WHERE terminal = ?
                    ORDER BY branch_id ASC, title ASC";
            
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, "i", $terminal);
        } else {
            // Get kitchens for specific branch - filter by branch_id AND terminal
            $sql = "SELECT 
                        kitchen_id,
                        title,
                        code,
                        printer,
                        branch_id,
                        terminal
                    FROM kitchens
                    WHERE branch_id = ? AND terminal = ?
                    ORDER BY title ASC";
            
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, "ii", $branch_id, $terminal);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result) {
            throw new Exception("Error executing query: " . mysqli_error($connection));
        }
        
        $kitchens = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $kitchens[] = [
                "kitchen_id" => intval($row['kitchen_id']),
                "title" => $row['title'] ?? '',
                "code" => $row['code'] ?? '',
                "printer" => $row['printer'] ?? '',
                "branch_id" => intval($row['branch_id']),
                "terminal" => intval($row['terminal'])
            ];
        }
        mysqli_stmt_close($stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        // Return array directly (frontend expects this format)
        // Ensure we always return valid JSON, even if empty
        $json_output = json_encode($kitchens);
        if ($json_output === false) {
            throw new Exception("Error encoding JSON: " . json_last_error_msg());
        }
        
        echo $json_output;
        
        exit();
    }
    
    // Handle CREATE action
    if ($action === 'create') {
        $title = isset($input['title']) ? trim($input['title']) : '';
        $code = isset($input['code']) ? trim($input['code']) : '';
        $printer = isset($input['printer']) ? trim($input['printer']) : '';
        
        if (empty($title)) {
            throw new Exception("Kitchen title is required");
        }
        
        if (empty($code)) {
            throw new Exception("Kitchen code is required");
        }
        
        // branch_id is required for create
        if ($branch_id === null || $branch_id <= 0) {
            throw new Exception("branch_id is required for creating a kitchen");
        }
        
        // Validate printer assignment if provided
        $printer_id = null;
        if (!empty($printer)) {
            if (is_numeric($printer)) {
                $printer_id = intval($printer);
                
                // Verify printer exists and is valid for this branch
                $printer_check_sql = "SELECT printer_id, connection_type, branch_id, type 
                                     FROM printers 
                                     WHERE printer_id = ? AND connection_type = 'network'";
                $printer_check_stmt = mysqli_prepare($connection, $printer_check_sql);
                if ($printer_check_stmt) {
                    mysqli_stmt_bind_param($printer_check_stmt, "i", $printer_id);
                    mysqli_stmt_execute($printer_check_stmt);
                    $printer_check_result = mysqli_stmt_get_result($printer_check_stmt);
                    $printer_data = mysqli_fetch_assoc($printer_check_result);
                    mysqli_stmt_close($printer_check_stmt);
                    
                    if (!$printer_data) {
                        throw new Exception("Printer ID $printer_id not found or is not a network printer. Kitchen printers must use network connection.");
                    }
                    
                    // Check if printer belongs to the same branch (or is global)
                    if (!empty($printer_data['branch_id']) && $printer_data['branch_id'] != $branch_id) {
                        throw new Exception("Printer belongs to a different branch (Branch ID: " . $printer_data['branch_id'] . "). Please select a printer for branch ID: $branch_id or use a global printer.");
                    }
                }
            } else {
                // If printer is not numeric, it might be an IP address (legacy support)
                // Allow it but log a warning
                error_log("Warning: Kitchen '$title' assigned non-numeric printer value: $printer");
            }
        }
        
        // Check if code already exists for this branch
        $check_sql = "SELECT kitchen_id FROM kitchens WHERE code = ? AND terminal = ? AND branch_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "sii", $code, $terminal, $branch_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            mysqli_stmt_close($check_stmt);
            throw new Exception("Kitchen code '$code' already exists for this branch");
        }
        mysqli_stmt_close($check_stmt);
        
        // Insert new kitchen
        $insert_sql = "INSERT INTO kitchens (code, title, printer, terminal, branch_id, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $insert_stmt = mysqli_prepare($connection, $insert_sql);
        
        if (!$insert_stmt) {
            throw new Exception("Error preparing insert statement: " . mysqli_error($connection));
        }
        
        // Use printer_id if validated, otherwise use original printer value
        $printer_value = ($printer_id !== null) ? $printer_id : $printer;
        mysqli_stmt_bind_param($insert_stmt, "sssii", $code, $title, $printer_value, $terminal, $branch_id);
        
        if (!mysqli_stmt_execute($insert_stmt)) {
            $error = mysqli_stmt_error($insert_stmt);
            mysqli_stmt_close($insert_stmt);
            throw new Exception("Error creating kitchen: " . $error);
        }
        
        $kitchen_id = mysqli_insert_id($connection);
        mysqli_stmt_close($insert_stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        // Get branch information for the response
        $branch_sql = "SELECT branch_name, branch_code FROM branches WHERE branch_id = ?";
        $branch_stmt = mysqli_prepare($connection, $branch_sql);
        $branch_name = '';
        $branch_code = '';
        if ($branch_stmt) {
            mysqli_stmt_bind_param($branch_stmt, "i", $branch_id);
            mysqli_stmt_execute($branch_stmt);
            $branch_result = mysqli_stmt_get_result($branch_stmt);
            if ($branch_row = mysqli_fetch_assoc($branch_result)) {
                $branch_name = $branch_row['branch_name'] ?? '';
                $branch_code = $branch_row['branch_code'] ?? '';
            }
            mysqli_stmt_close($branch_stmt);
        }
        
        echo json_encode([
            "success" => true,
            "message" => "Kitchen created successfully",
            "data" => [
                "kitchen_id" => $kitchen_id,
                "code" => $code,
                "title" => $title,
                "printer" => $printer,
                "branch_id" => $branch_id,
                "branch_name" => $branch_name,
                "branch_code" => $branch_code,
                "terminal" => $terminal
            ]
        ]);
        exit();
    }
    
    // Handle UPDATE action
    if ($action === 'update') {
        $kitchen_id = isset($input['kitchen_id']) ? intval($input['kitchen_id']) : 0;
        $title = isset($input['title']) ? trim($input['title']) : '';
        $code = isset($input['code']) ? trim($input['code']) : '';
        $printer = isset($input['printer']) ? trim($input['printer']) : '';
        
        if (empty($kitchen_id) || $kitchen_id <= 0) {
            throw new Exception("Kitchen ID is required for update");
        }
        
        if (empty($title)) {
            throw new Exception("Kitchen title is required");
        }
        
        if (empty($code)) {
            throw new Exception("Kitchen code is required");
        }
        
        // branch_id is required for update
        if ($branch_id === null || $branch_id <= 0) {
            throw new Exception("branch_id is required for updating a kitchen");
        }
        
        // Validate printer assignment if provided
        $printer_id = null;
        if (!empty($printer)) {
            if (is_numeric($printer)) {
                $printer_id = intval($printer);
                
                // Verify printer exists and is valid for this branch
                $printer_check_sql = "SELECT printer_id, connection_type, branch_id, type 
                                     FROM printers 
                                     WHERE printer_id = ? AND connection_type = 'network'";
                $printer_check_stmt = mysqli_prepare($connection, $printer_check_sql);
                if ($printer_check_stmt) {
                    mysqli_stmt_bind_param($printer_check_stmt, "i", $printer_id);
                    mysqli_stmt_execute($printer_check_stmt);
                    $printer_check_result = mysqli_stmt_get_result($printer_check_stmt);
                    $printer_data = mysqli_fetch_assoc($printer_check_result);
                    mysqli_stmt_close($printer_check_stmt);
                    
                    if (!$printer_data) {
                        throw new Exception("Printer ID $printer_id not found or is not a network printer. Kitchen printers must use network connection.");
                    }
                    
                    // Check if printer belongs to the same branch (or is global)
                    if (!empty($printer_data['branch_id']) && $printer_data['branch_id'] != $branch_id) {
                        throw new Exception("Printer belongs to a different branch (Branch ID: " . $printer_data['branch_id'] . "). Please select a printer for branch ID: $branch_id or use a global printer.");
                    }
                }
            } else {
                // If printer is not numeric, it might be an IP address (legacy support)
                // Allow it but log a warning
                error_log("Warning: Kitchen '$title' (ID: $kitchen_id) assigned non-numeric printer value: $printer");
            }
        }
        
        // Check if code already exists for another kitchen in this branch
        $check_sql = "SELECT kitchen_id FROM kitchens WHERE code = ? AND terminal = ? AND branch_id = ? AND kitchen_id != ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "siii", $code, $terminal, $branch_id, $kitchen_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            mysqli_stmt_close($check_stmt);
            throw new Exception("Kitchen code '$code' already exists for another kitchen in this branch");
        }
        mysqli_stmt_close($check_stmt);
        
        // Update kitchen
        $update_sql = "UPDATE kitchens 
                       SET code = ?, title = ?, printer = ?, updated_at = NOW()
                       WHERE kitchen_id = ? AND terminal = ? AND branch_id = ?";
        $update_stmt = mysqli_prepare($connection, $update_sql);
        
        // Use printer_id if validated, otherwise use original printer value
        $printer_value = ($printer_id !== null) ? $printer_id : $printer;
        mysqli_stmt_bind_param($update_stmt, "sssiii", $code, $title, $printer_value, $kitchen_id, $terminal, $branch_id);
        mysqli_stmt_execute($update_stmt);
        $affected = mysqli_affected_rows($connection);
        mysqli_stmt_close($update_stmt);
        
        if ($affected > 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            // Get branch information for the response
            $branch_sql = "SELECT branch_name, branch_code FROM branches WHERE branch_id = ?";
            $branch_stmt = mysqli_prepare($connection, $branch_sql);
            $branch_name = '';
            $branch_code = '';
            if ($branch_stmt) {
                mysqli_stmt_bind_param($branch_stmt, "i", $branch_id);
                mysqli_stmt_execute($branch_stmt);
                $branch_result = mysqli_stmt_get_result($branch_stmt);
                if ($branch_row = mysqli_fetch_assoc($branch_result)) {
                    $branch_name = $branch_row['branch_name'] ?? '';
                    $branch_code = $branch_row['branch_code'] ?? '';
                }
                mysqli_stmt_close($branch_stmt);
            }
            
            echo json_encode([
                "success" => true,
                "message" => "Kitchen updated successfully",
                "data" => [
                    "kitchen_id" => $kitchen_id,
                    "code" => $code,
                    "title" => $title,
                    "printer" => $printer,
                    "branch_id" => $branch_id,
                    "branch_name" => $branch_name,
                    "branch_code" => $branch_code,
                    "terminal" => $terminal
                ]
            ]);
        } else {
            throw new Exception("Kitchen not found or no changes made");
        }
        exit();
    }
    
    throw new Exception("Invalid action. Must be 'get', 'create', or 'update'");
    
} catch (Exception $e) {
    error_log("Kitchen Management Error: " . $e->getMessage());
    error_log("Kitchen Management Error Trace: " . $e->getTraceAsString());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    $error_response = [
        "success" => false,
        "message" => $e->getMessage()
    ];
    
    $json_output = json_encode($error_response);
    if ($json_output === false) {
        $json_output = json_encode([
            "success" => false,
            "message" => "An error occurred. Please try again."
        ]);
    }
    
    echo $json_output;
    exit();
    
} catch (Error $e) {
    error_log("Kitchen Management Fatal Error: " . $e->getMessage());
    error_log("Kitchen Management Fatal Error Trace: " . $e->getTraceAsString());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => false,
        "message" => "Fatal error: " . $e->getMessage()
    ]);
    exit();
}

exit();
?>
