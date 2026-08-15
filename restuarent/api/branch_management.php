<?php

/**
 * Branch Management API
 * CRUD operations for restaurant branches
 * 
 * POST Parameters (JSON):
 * - branch_id (int, optional) - For update, empty for create
 * - branch_name (string, required) - Branch name
 * - branch_code (string, required) - Unique branch code
 * - address (string, optional) - Branch address
 * - phone (string, optional) - Branch phone
 * - email (string, optional) - Branch email
 * - status (string, optional) - Active/Inactive
 * 
 * DELETE Parameters (JSON):
 * - branch_id (int, required) - Branch ID to delete
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
    
    // Handle OPTIONS request for CORS
    if ($method === 'OPTIONS') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        exit();
    }
    
    // Get input data - handle both JSON and form data
    $input = [];
    $raw_input = file_get_contents('php://input');
    
    // For POST requests, try JSON body first
    if ($method === 'POST' && $raw_input) {
        $json_input = json_decode($raw_input, true);
        if ($json_input && is_array($json_input)) {
            $input = $json_input;
        }
    }
    
    // Fallback to POST form data
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    // For GET requests, use query parameters
    if ($method === 'GET' && empty($input)) {
        if (!empty($_GET)) {
            $input = $_GET;
        }
    }
    
    $action = isset($input['action']) ? trim($input['action']) : '';
    
    // GET or POST with action='get' - Get all branches or single branch
    // Check this FIRST before create/update validation
    if ($method === 'GET' || $action === 'get') {
        // Get branch_id from GET or POST
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : (isset($input['branch_id']) ? intval($input['branch_id']) : 0);
        
        if ($branch_id > 0) {
            // Get single branch
            $sql = "SELECT * FROM branches WHERE branch_id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, "i", $branch_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error executing query: " . mysqli_error($connection));
            }
            
            $result = mysqli_stmt_get_result($stmt);
            
            if (!$result) {
                throw new Exception("Error getting result: " . mysqli_error($connection));
            }
            
            $branch = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            if ($branch) {
                echo json_encode([
                    "success" => true,
                    "data" => $branch
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "Branch not found"
                ]);
            }
        } else {
            // Get all branches
            $sql = "SELECT * FROM branches ORDER BY branch_name ASC";
            $result = mysqli_query($connection, $sql);
            
            if (!$result) {
                throw new Exception("Error executing query: " . mysqli_error($connection));
            }
            
            $branches = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $branches[] = $row;
            }
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            // Always return proper structure, even if empty
            echo json_encode([
                "success" => true,
                "data" => $branches,
                "count" => count($branches)
            ]);
        }
        exit();
    }
    
    // DELETE - Delete branch
    if ($method === 'DELETE') {
        $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
        
        if (empty($branch_id) || $branch_id <= 0) {
            throw new Exception("Branch ID is required for deletion");
        }
        
        // Check if branch has orders or users
        $check_orders = "SELECT COUNT(*) as count FROM orders WHERE branch_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_orders);
        mysqli_stmt_bind_param($check_stmt, "i", $branch_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $order_count = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($check_stmt);
        
        if ($order_count > 0) {
            throw new Exception("Cannot delete branch. It has existing orders. Please deactivate it instead.");
        }
        
        $sql = "DELETE FROM branches WHERE branch_id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $branch_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error deleting branch: " . mysqli_error($connection));
        }
        
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        if ($affected_rows > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Branch deleted successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Branch not found"
            ]);
        }
        exit();
    }
    
    // POST - Create or Update
    if ($method === 'POST') {
        // If POST has no data or only has action='get', treat as GET request
        if (empty($input) || (isset($input['action']) && $input['action'] === 'get')) {
            // Treat as GET request
            $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : (isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0);
            
            if ($branch_id > 0) {
                // Get single branch
                $sql = "SELECT * FROM branches WHERE branch_id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                
                if (!$stmt) {
                    throw new Exception("Error preparing statement: " . mysqli_error($connection));
                }
                
                mysqli_stmt_bind_param($stmt, "i", $branch_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error executing query: " . mysqli_error($connection));
                }
                
                $result = mysqli_stmt_get_result($stmt);
                
                if (!$result) {
                    throw new Exception("Error getting result: " . mysqli_error($connection));
                }
                
                $branch = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Content-Type: application/json; charset=UTF-8");
                }
                
                if ($branch) {
                    echo json_encode([
                        "success" => true,
                        "data" => $branch
                    ]);
                } else {
                    echo json_encode([
                        "success" => false,
                        "message" => "Branch not found"
                    ]);
                }
            } else {
                // Get all branches
                $sql = "SELECT * FROM branches ORDER BY branch_name ASC";
                $result = mysqli_query($connection, $sql);
                
                if (!$result) {
                    throw new Exception("Error executing query: " . mysqli_error($connection));
                }
                
                $branches = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $branches[] = $row;
                }
                
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Content-Type: application/json; charset=UTF-8");
                }
                
                echo json_encode([
                    "success" => true,
                    "data" => $branches,
                    "count" => count($branches)
                ]);
            }
            exit();
        }
        
        $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
        $branch_name = isset($input['branch_name']) ? trim($input['branch_name']) : '';
        $branch_code = isset($input['branch_code']) ? trim($input['branch_code']) : '';
        $address = isset($input['address']) ? trim($input['address']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : '';
        $email = isset($input['email']) ? trim($input['email']) : '';
        $status = isset($input['status']) ? trim($input['status']) : 'Active';
        
        // Validate required fields
        if (empty($branch_name)) {
            throw new Exception("Branch name is required");
        }
        
        if (empty($branch_code)) {
            throw new Exception("Branch code is required");
        }
        
        // Validate status
        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }
        
        if ($branch_id > 0) {
            // Update existing branch
            // Check if branch_code is unique (excluding current branch)
            $check_sql = "SELECT branch_id FROM branches WHERE branch_code = ? AND branch_id != ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "si", $branch_code, $branch_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            if (mysqli_num_rows($result) > 0) {
                mysqli_stmt_close($check_stmt);
                throw new Exception("Branch code already exists");
            }
            mysqli_stmt_close($check_stmt);
            
            $sql = "UPDATE branches SET 
                    branch_name = ?,
                    branch_code = ?,
                    address = ?,
                    phone = ?,
                    email = ?,
                    status = ?,
                    updated_at = NOW()
                    WHERE branch_id = ?";
            
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, "ssssssi", $branch_name, $branch_code, $address, $phone, $email, $status, $branch_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error updating branch: " . mysqli_error($connection));
            }
            
            mysqli_stmt_close($stmt);
            
            $message = "Branch updated successfully";
        } else {
            // Create new branch
            // Check if branch_code is unique
            $check_sql = "SELECT branch_id FROM branches WHERE branch_code = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $branch_code);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            if (mysqli_num_rows($result) > 0) {
                mysqli_stmt_close($check_stmt);
                throw new Exception("Branch code already exists");
            }
            mysqli_stmt_close($check_stmt);
            
            $sql = "INSERT INTO branches (branch_name, branch_code, address, phone, email, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, "ssssss", $branch_name, $branch_code, $address, $phone, $email, $status);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error creating branch: " . mysqli_error($connection));
            }
            
            $branch_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt);
            
            $message = "Branch created successfully";
        }
        
        // Get updated branch
        $get_sql = "SELECT * FROM branches WHERE branch_id = ?";
        $get_stmt = mysqli_prepare($connection, $get_sql);
        mysqli_stmt_bind_param($get_stmt, "i", $branch_id);
        mysqli_stmt_execute($get_stmt);
        $result = mysqli_stmt_get_result($get_stmt);
        $branch = mysqli_fetch_assoc($result);
        mysqli_stmt_close($get_stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "message" => $message,
            "data" => $branch
        ]);
        exit();
    }
    
    // If we reach here, it's an invalid request
    throw new Exception("Invalid request method or missing required parameters");
    
} catch (Exception $e) {
    error_log("Branch Management Error: " . $e->getMessage());
    
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

