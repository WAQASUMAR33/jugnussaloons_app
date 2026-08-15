<?php

/**
 * Get Kitchens API - Multi-Branch Support
 * Returns list of all kitchens for a specific branch
 * 
 * POST Parameters:
 * - terminal (int, optional) - Terminal number (default: 1)
 * - branch_id (int, optional) - Branch ID (if not provided, uses terminal)
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

// Ensure connection is alive
if (isset($connection) && $connection) {
    if (!mysqli_ping($connection)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["success" => false, "message" => "Database connection lost"]);
        exit();
    }
}

try {
    // Handle both GET and POST requests
    $input = [];
    
    // For POST requests, try JSON body first
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw_input = file_get_contents('php://input');
        if ($raw_input) {
            $json_input = json_decode($raw_input, true);
            if ($json_input && is_array($json_input)) {
                $input = $json_input;
            }
        }
        // Fallback to POST form data
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }
    }
    
    // For GET requests, use query parameters
    if ($_SERVER['REQUEST_METHOD'] === 'GET' || empty($input)) {
        if (!empty($_GET)) {
            $input = $_GET;
        }
    }
    
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
    
    // If branch_id is not provided, try to get it from terminal or use terminal as branch_id
    if (empty($branch_id) || $branch_id <= 0) {
        $branch_id = $terminal; // Fallback to terminal if branch_id not provided
    }
    
    // Check if kitchens table exists
    $check_table = mysqli_query($connection, "SHOW TABLES LIKE 'kitchens'");
    if (!$check_table || mysqli_num_rows($check_table) === 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode([
            "success" => false,
            "message" => "Kitchens table does not exist",
            "data" => []
        ]);
        exit();
    }
    
    // Check if branches table exists for JOIN
    $check_branches = mysqli_query($connection, "SHOW TABLES LIKE 'branches'");
    $has_branches_table = ($check_branches && mysqli_num_rows($check_branches) > 0);
    
    // Build query - use simpler approach if branches table doesn't exist
    if ($has_branches_table) {
        // Join with branches table to get branch information
        if ($branch_id == $terminal) {
            // Show all kitchens when branch_id is fallback (backward compatibility)
            $sql = "SELECT k.kitchen_id, k.code, k.title, k.printer, k.terminal, k.branch_id, 
                           k.created_at, k.updated_at,
                           COALESCE(b.branch_name, CONCAT('Branch ', k.branch_id)) AS branch_name,
                           b.branch_code
                    FROM kitchens k
                    LEFT JOIN branches b ON k.branch_id = b.branch_id
                    ORDER BY k.branch_id ASC, k.kitchen_id ASC";
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                $error = mysqli_error($connection);
                throw new Exception("Error preparing statement: " . ($error ?: "Unknown error"));
            }
        } else {
            // Filter by specific branch_id
            $sql = "SELECT k.kitchen_id, k.code, k.title, k.printer, k.terminal, k.branch_id, 
                           k.created_at, k.updated_at,
                           COALESCE(b.branch_name, CONCAT('Branch ', k.branch_id)) AS branch_name,
                           b.branch_code
                    FROM kitchens k
                    LEFT JOIN branches b ON k.branch_id = b.branch_id
                    WHERE k.branch_id = ?
                    ORDER BY k.kitchen_id ASC";
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                $error = mysqli_error($connection);
                throw new Exception("Error preparing statement: " . ($error ?: "Unknown error"));
            }
            mysqli_stmt_bind_param($stmt, "i", $branch_id);
        }
    } else {
        // Fallback: Query without branches table
        if ($branch_id == $terminal) {
            $sql = "SELECT k.kitchen_id, k.code, k.title, k.printer, k.terminal, k.branch_id, 
                           k.created_at, k.updated_at,
                           CONCAT('Branch ', k.branch_id) AS branch_name,
                           NULL AS branch_code
                    FROM kitchens k
                    ORDER BY k.branch_id ASC, k.kitchen_id ASC";
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                $error = mysqli_error($connection);
                throw new Exception("Error preparing statement: " . ($error ?: "Unknown error"));
            }
        } else {
            $sql = "SELECT k.kitchen_id, k.code, k.title, k.printer, k.terminal, k.branch_id, 
                           k.created_at, k.updated_at,
                           CONCAT('Branch ', k.branch_id) AS branch_name,
                           NULL AS branch_code
                    FROM kitchens k
                    WHERE k.branch_id = ?
                    ORDER BY k.kitchen_id ASC";
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                $error = mysqli_error($connection);
                throw new Exception("Error preparing statement: " . ($error ?: "Unknown error"));
            }
            mysqli_stmt_bind_param($stmt, "i", $branch_id);
        }
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        throw new Exception("Error executing query: " . ($error ?: "Unknown error"));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        throw new Exception("Error getting result: " . ($error ?: "Unknown error"));
    }
    
    $kitchens = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Ensure all expected fields are present
        $kitchens[] = [
            'kitchen_id' => intval($row['kitchen_id'] ?? 0),
            'code' => $row['code'] ?? '',
            'title' => $row['title'] ?? '',
            'printer' => $row['printer'] ?? null,
            'terminal' => intval($row['terminal'] ?? 1),
            'branch_id' => intval($row['branch_id'] ?? 0),
            'branch_name' => $row['branch_name'] ?? 'N/A',
            'branch_code' => $row['branch_code'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null
        ];
    }
    mysqli_stmt_close($stmt);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Return in documented format: {success: true, data: [...]}
    $json_output = json_encode([
        "success" => true,
        "data" => $kitchens,
        "count" => count($kitchens)
    ], JSON_UNESCAPED_UNICODE);
    
    if ($json_output === false) {
        throw new Exception('JSON encoding failed: ' . json_last_error_msg());
    }
    
    echo $json_output;
    
} catch (Exception $e) {
    error_log("Get Kitchens Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error fetching kitchens: " . $e->getMessage(),
        "data" => []
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    error_log("Get Kitchens Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Fatal error: " . $e->getMessage(),
        "data" => []
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

exit();
?>
