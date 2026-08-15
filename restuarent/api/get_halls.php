<?php
// FIX: Start output buffer tracking at the absolute top execution line boundary
ob_start();

require_once 'cors_headers.php';

/**
 * Get Halls API
 * Returns halls filtered by branch_id (optional)
 * - If branch_id is null/empty → returns ALL halls from ALL branches (Super-Admin)
 * - If branch_id is provided → returns only halls for that branch (Branch-Admin)
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
            http_response_code(500); // FIX: Provide proper HTTP status codes on runtime failure
        }
        
        echo json_encode([
            "success" => false,
            "message" => "Fatal runtime engine error occurred."
        ]);
        exit();
    }
});

// Include config safely within a unified structural Throwable architecture
try {
    include("config.php");
} catch (Throwable $e) { // FIX: Replaced isolated Exception/Error scopes with a single catch statement
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(500);
    }
    echo json_encode(["success" => false, "message" => "Internal endpoint configurations are unreadable."]);
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
        http_response_code(405); // FIX: Enforce accurate Method Not Allowed definitions
    }
    echo json_encode(["success" => false, "message" => "Method not allowed. Use GET or POST."]);
    exit();
}

try {
    // Check connection early before reading payloads
    if (!isset($connection) || !$connection) {
        http_response_code(500);
        throw new Exception("Database link engine unavailable.");
    }

    // Get input data - handle both GET and POST
    if ($method === 'GET') {
        $input = $_GET;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            $input = $_POST;
        }
    }

    // Get branch_id from input data (optional)
    $branch_id_input = isset($input["branch_id"]) ? $input["branch_id"] : null;
    $branch_id = null;

    // Validate branch_id if provided
    if ($branch_id_input !== null && $branch_id_input !== '' && $branch_id_input !== 'null' && $branch_id_input !== 'undefined') {
        $branch_id = intval($branch_id_input);
        if ($branch_id <= 0) {
            http_response_code(400); // Bad Request
            throw new Exception("Invalid branch_id logic configuration mapping parameters.");
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
                http_response_code(404); // Not Found
                throw new Exception("Target branch profile does not exist within tracking matrices.");
            }
            mysqli_stmt_close($check_branch_stmt);
        }
    }

    // Get terminal from input data
    $terminal = isset($input["terminal"]) ? intval($input["terminal"]) : 1;

    // Build query with JOIN to branches table
    if ($branch_id !== null) {
        // Branch-Admin scenario: Filter by branch_id and terminal
        $sql = "SELECT h.*, 
                COALESCE(b.branch_name, CONCAT('Branch ', h.branch_id)) AS branch_name,
                h.branch_id
                FROM halls h
                LEFT JOIN branches b ON h.branch_id = b.branch_id
                WHERE h.branch_id = ? AND h.terminal = ?
                ORDER BY h.hall_id DESC";
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            throw new Exception("Failed preparing filtered layout location records.");
        }
        mysqli_stmt_bind_param($stmt, "ii", $branch_id, $terminal);
    } else {
        // Super-Admin scenario: FIX: Removed strict c.terminal mapping dependencies to return ALL layout regions enterprise-wide
        $sql = "SELECT h.*, 
                COALESCE(b.branch_name, CONCAT('Branch ', h.branch_id)) AS branch_name,
                h.branch_id
                FROM halls h
                LEFT JOIN branches b ON h.branch_id = b.branch_id
                ORDER BY h.branch_id ASC, h.hall_id DESC";
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            throw new Exception("Failed preparing master location tracking components.");
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception("Execution pipeline error structural layout tracing: " . $error);
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Failed reading operational result data mappings.");
    }
    
    $emparray = array();
    while($row = mysqli_fetch_assoc($result)) {
        // Sanitize database metrics output layout safely to eliminate XSS surface vectors
        if (isset($row['name'])) {
            $row['name'] = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
        }
        
        if (!isset($row['branch_id']) || $row['branch_id'] === null) {
            $row['branch_id'] = null;
        } else {
            $row['branch_id'] = intval($row['branch_id']);
        }
        
        if (!isset($row['branch_name']) || empty($row['branch_name'])) {
            $row['branch_name'] = $row['branch_id'] ? 'Branch ' . $row['branch_id'] : 'No Branch';
        } else {
            $row['branch_name'] = htmlspecialchars($row['branch_name'], ENT_QUOTES, 'UTF-8');
        }
        
        $emparray[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(200);
    }
    
    $response_data = [
        'success' => true,
        'data' => $emparray,
        'count' => count($emparray)
    ];
    
    $json_output = json_encode($response_data);
    if ($json_output === false) {
        throw new Exception("Failed to serialize database payload layout adjustments.");
    }
    
    echo $json_output;
    exit();

} catch (Throwable $e) { // FIX: Gracefully captures runtime calculation faults and database crashes together
    error_log("Get Halls Processing Breakdown: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        if (http_response_code() === 200) {
            http_response_code(500);
        }
    }
    
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
}
?>