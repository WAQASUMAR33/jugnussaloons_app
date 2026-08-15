<?php
/**
 * Get Users Accounts API
 * Returns all users for a specific terminal
 * Supports both JSON and form data
 * 
 * POST Parameters:
 * - terminal (int, optional) - Terminal number (default: 1)
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 1,
 *       "username": "user1",
 *       "fullname": "User One",
 *       "role": "branch_admin",
 *       "status": "Active",
 *       "terminal": 1,
 *       "branch_id": 1,
 *       "branch_name": "Main Branch",
 *       "branch_code": "MB001",
 *       "created_at": "2025-01-01 10:00:00",
 *       "updated_at": "2025-01-01 10:00:00"
 *     }
 *   ]
 * }
 */

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering
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
    echo json_encode([
        "success" => false,
        "message" => "Configuration error: " . $e->getMessage()
    ]);
    exit();
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed. Use POST."
    ]);
    exit();
}

// Get input data - handle both JSON and form data
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (!$input || !is_array($input) || empty($input)) {
    $input = $_POST; // Fallback to form data
}

// Get terminal from input data
$terminal = isset($input["terminal"]) ? intval($input["terminal"]) : (isset($_POST["terminal"]) ? intval($_POST["terminal"]) : 1);

// Check connection
if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit();
}

try {
    // Query to get users with branch information (excluding password for security)
    $sql = "SELECT u.id, u.username, u.fullname, u.role, u.status, u.terminal, u.branch_id, u.created_at, u.updated_at, 
            b.branch_name, b.branch_code 
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.branch_id
            WHERE u.terminal = ? 
            ORDER BY u.id DESC";
    
    $stmt = mysqli_prepare($connection, $sql);
    
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $terminal);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error executing query: " . mysqli_error($connection));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception("Error getting result: " . mysqli_error($connection));
    }
    
    // Create array
    $users = array();
    while($row = mysqli_fetch_assoc($result)) {
        // Ensure all fields are properly formatted
        $users[] = [
            'id' => intval($row['id'] ?? 0),
            'username' => $row['username'] ?? '',
            'fullname' => $row['fullname'] ?? '',
            'role' => $row['role'] ?? '',
            'status' => $row['status'] ?? 'Active',
            'terminal' => intval($row['terminal'] ?? 1),
            'branch_id' => $row['branch_id'] ? intval($row['branch_id']) : null,
            'branch_name' => $row['branch_name'] ?? null,
            'branch_code' => $row['branch_code'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Clear output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Return proper JSON structure with success and data
    echo json_encode([
        "success" => true,
        "data" => $users,
        "count" => count($users)
    ]);
    
} catch (Exception $e) {
    // Clear output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error fetching users: " . $e->getMessage()
    ]);
    exit();
}

// Don't close connection here - let it be managed by config.php
exit();
?>
