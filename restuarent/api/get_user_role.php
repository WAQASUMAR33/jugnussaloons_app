<?php

/**
 * Get User Role API
 * Fetches the actual role from database when login API returns boolean or needs verification
 * 
 * POST Parameters (JSON):
 * - user_id (int, optional) - User ID
 * - username (string, optional) - Username
 * 
 * Response:
 * {
 *   "success": true,
 *   "role": "super_admin",
 *   "message": "Role fetched successfully"
 * }
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
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
    $username = isset($input['username']) ? trim($input['username']) : '';
    
    if (empty($user_id) && empty($username)) {
        throw new Exception("User ID or username is required");
    }
    
    // Build query based on provided parameter - Include branch information
    if ($user_id > 0) {
        $sql = "SELECT u.role, u.id, u.username, u.fullname, u.branch_id, b.branch_name, b.branch_code 
                FROM users u
                LEFT JOIN branches b ON u.branch_id = b.branch_id
                WHERE u.id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else {
        $sql = "SELECT u.role, u.id, u.username, u.fullname, u.branch_id, b.branch_name, b.branch_code 
                FROM users u
                LEFT JOIN branches b ON u.branch_id = b.branch_id
                WHERE u.username = ?";
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "s", $username);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Ensure role is a string, not boolean
        $role = $user['role'] ?? 'order_taker';
        
        // If role is somehow boolean true, set default
        if ($role === true || $role === 1) {
            $role = 'order_taker';
        }
        
        // If role is somehow boolean false or empty, set default
        if ($role === false || $role === 0 || empty($role)) {
            $role = 'order_taker';
        }
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "role" => $role,
            "user_id" => intval($user['id']),
            "username" => $user['username'],
            "fullname" => $user['fullname'] ?? $user['username'],
            "branch_id" => $user['branch_id'] ? intval($user['branch_id']) : null,
            "branch_name" => $user['branch_name'] ?? null,
            "branch_code" => $user['branch_code'] ?? null,
            "message" => "Role fetched successfully"
        ]);
    } else {
        throw new Exception("User not found");
    }
    
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    error_log("Get User Role Error: " . $e->getMessage());
    
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

