<?php
/**
 * Create Super Admin API
 * Creates a default super admin user with full access to all branches
 * 
 * POST Parameters (JSON, optional):
 * - username (string, optional) - Default: 'admin@gmail.com'
 * - password (string, optional) - Default: 'dev786'
 * - fullname (string, optional) - Default: 'Super Admin'
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Super admin created successfully!",
 *   "user": { ... }
 * }
 */
require_once 'cors_headers.php';

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
    
    // Super admin credentials (can be overridden via POST)
    $username = isset($input['username']) ? trim($input['username']) : 'admin@gmail.com';
    $password = isset($input['password']) ? trim($input['password']) : 'dev786';
    $fullname = isset($input['fullname']) ? trim($input['fullname']) : 'Super Admin';
    $role = 'super_admin';
    $status = 'Active';
    $branch_id = null; // Super admin has no branch_id (manages all branches)
    
    // Validate inputs
    if (empty($username)) {
        throw new Exception("Username is required");
    }
    
    if (empty($password)) {
        throw new Exception("Password is required");
    }
    
    if (empty($fullname)) {
        throw new Exception("Full name is required");
    }
    
    // Check if super admin already exists
    $check_sql = "SELECT id, username FROM users WHERE username = ? AND role = ?";
    $check_stmt = mysqli_prepare($connection, $check_sql);
    
    if (!$check_stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($check_stmt, "ss", $username, $role);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $existing_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check_stmt);
    
    if ($existing_user) {
        // Super admin already exists
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode([
            "success" => false,
            "message" => "Super admin already exists with username: " . $existing_user['username'],
            "user_id" => $existing_user['id']
        ]);
        exit();
    }
    
    // Hash the password using password_hash (bcrypt)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    
    // Insert super admin
    // Note: branch_id is NULL for super_admin (they manage all branches)
    $insert_sql = "INSERT INTO users (username, password, fullname, token, role, branch_id, status, terminal, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
    
    $stmt = mysqli_prepare($connection, $insert_sql);
    
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, "sssssis", $username, $hashedPassword, $fullname, $token, $role, $branch_id, $status);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error creating super admin: " . mysqli_error($connection));
    }
    
    $user_id = mysqli_insert_id($connection);
    mysqli_stmt_close($stmt);
    
    // Get the created user
    $get_sql = "SELECT u.*, b.branch_name, b.branch_code 
                FROM users u
                LEFT JOIN branches b ON u.branch_id = b.branch_id
                WHERE u.id = ?";
    $get_stmt = mysqli_prepare($connection, $get_sql);
    mysqli_stmt_bind_param($get_stmt, "i", $user_id);
    mysqli_stmt_execute($get_stmt);
    $result = mysqli_stmt_get_result($get_stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($get_stmt);
    
    // Remove password from response
    unset($user['password']);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Super admin created successfully!",
        "user" => $user,
        "credentials" => [
            "username" => $username,
            "password" => $password, // Only shown on creation
            "role" => $role,
            "branch_access" => "All branches (branch_id: null)"
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Create Super Admin Error: " . $e->getMessage());
    
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

