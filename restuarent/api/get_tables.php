<?php
/**
 * Get Tables API
 * Returns tables filtered by branch_id (optional)
 * - If branch_id is null/empty → returns ALL tables from ALL branches (Super-Admin)
 * - If branch_id is provided → returns only tables for that branch (Branch-Admin)
 * Supports both JSON and form data
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

// Include config after headers
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

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    exit();
}

// Support both GET and POST methods
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Method not allowed. Use GET or POST."]);
    exit();
}

try {
    // Get input data - handle both GET and POST
    if ($method === 'GET') {
        // For GET requests, use query parameters
        $input = $_GET;
    } else {
        // For POST requests, handle both JSON and form data
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST; // Fallback to form data
        }
    }

    // Get branch_id from input data (optional)
    $branch_id_input = isset($input["branch_id"]) ? $input["branch_id"] : null;
    $branch_id = null;

    // Validate branch_id if provided
    if ($branch_id_input !== null && $branch_id_input !== '' && $branch_id_input !== 'null' && $branch_id_input !== 'undefined') {
        $branch_id = intval($branch_id_input);
        if ($branch_id <= 0) {
            throw new Exception("Invalid branch_id. Must be a positive integer.");
        }
        
        // Validate branch exists
        $check_branch_sql = "SELECT branch_id FROM branches WHERE branch_id = ?";
        $check_branch_stmt = mysqli_prepare($connection, $check_branch_sql);
        if ($check_branch_stmt) {
            mysqli_stmt_bind_param($check_branch_stmt, "i", $branch_id);
            mysqli_stmt_execute($check_branch_stmt);
            $branch_result = mysqli_stmt_get_result($check_branch_stmt);
            if (mysqli_num_rows($branch_result) === 0) {
                mysqli_stmt_close($check_branch_stmt);
                throw new Exception("Branch not found with ID: " . $branch_id);
            }
            mysqli_stmt_close($check_branch_stmt);
        }
    }

    // Get terminal from input data
    $terminal = isset($input["terminal"]) ? intval($input["terminal"]) : (isset($_POST["terminal"]) ? intval($_POST["terminal"]) : 1);

    // Check connection
    if (!isset($connection) || !$connection) {
        throw new Exception("Database connection failed");
    }

    // Build query with JOINs to halls and branches tables
    // If branch_id is provided, filter by it; otherwise return all tables
    if ($branch_id !== null) {
        // Branch-Admin scenario: Filter by branch_id
        $sql = "SELECT t.*, 
                h.name AS hall_name,
                COALESCE(b.branch_name, CONCAT('Branch ', t.branch_id)) AS branch_name,
                t.branch_id
                FROM tables t
                LEFT JOIN halls h ON t.hall_id = h.hall_id
                LEFT JOIN branches b ON t.branch_id = b.branch_id
                WHERE t.branch_id = ? AND t.terminal = ?
                ORDER BY t.table_id DESC";
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "ii", $branch_id, $terminal);
    } else {
        // Super-Admin scenario: Return all tables from all branches (including NULL branch_id)
        $sql = "SELECT t.*, 
                h.name AS hall_name,
                COALESCE(b.branch_name, CONCAT('Branch ', t.branch_id)) AS branch_name,
                t.branch_id
                FROM tables t
                LEFT JOIN halls h ON t.hall_id = h.hall_id
                LEFT JOIN branches b ON t.branch_id = b.branch_id
                WHERE t.terminal = ?
                ORDER BY t.branch_id ASC, t.table_id DESC";
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $terminal);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        throw new Exception("Error executing query: " . $error);
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        throw new Exception("Error getting result: " . $error);
    }
    
    // Create array
    $emparray = array();
    $row_count = 0;
    while($row = mysqli_fetch_assoc($result)) {
        $row_count++;
        // Ensure branch_id and branch_name are included
        if (!isset($row['branch_id']) || $row['branch_id'] === null) {
            $row['branch_id'] = null;
        } else {
            $row['branch_id'] = intval($row['branch_id']);
        }
        if (!isset($row['branch_name']) || empty($row['branch_name'])) {
            $row['branch_name'] = $row['branch_id'] ? 'Branch ' . $row['branch_id'] : 'No Branch';
        }
        if (!isset($row['hall_name'])) {
            $row['hall_name'] = null;
        }
        $emparray[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    // Log for debugging (remove in production if needed)
    error_log("Get Tables: Found $row_count tables for branch_id=" . ($branch_id ?? 'null') . ", terminal=$terminal");
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Return success response with tables array (matching other GET APIs format)
    $response_data = [
        'success' => true,
        'data' => $emparray,
        'count' => count($emparray)
    ];
    
    $json_output = json_encode($response_data);
    if ($json_output === false) {
        throw new Exception("Error encoding JSON: " . json_last_error_msg());
    }
    
    // Flush output to ensure it's sent
    echo $json_output;
    flush();
    
} catch (Exception $e) {
    error_log("Get Tables Error: " . $e->getMessage());
    
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
    
} catch (Error $e) {
    error_log("Get Tables Fatal Error: " . $e->getMessage());
    
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

// Don't close connection here - let PHP handle it automatically
exit();
?>
