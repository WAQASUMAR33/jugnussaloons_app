<?php
/**
 * Login API - Multi-Branch Support
 * Authenticates users and returns token, role, and branch_id
 * 
 * POST Parameters (JSON):
 * - username (string, required)
 * - password (string, required)
 * 
 * Response:
 * {
 *   "success": true,
 *   "token": "abc123...",
 *   "role": "branch_admin",
 *   "branch_id": 1,
 *   "branch_name": "Main Branch",
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
} catch (Error $e) {
    // Catch fatal errors from config.php
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
    
    // Get detailed error message
    $error_msg = "Database connection failed";
    $connect_error = mysqli_connect_error();
    if ($connect_error) {
        $error_msg = "Database connection failed: " . $connect_error;
        
        // Provide helpful message for common errors
        if (strpos($connect_error, "Access denied") !== false) {
            $error_msg = "Database access denied. Please check your database credentials in config.php. " . 
                        "If MySQL requires a password, update DB_PASS in config.php";
        } elseif (strpos($connect_error, "Unknown database") !== false) {
            $error_msg = "Database not found. Please check DB_NAME in config.php";
        }
    }
    
    echo json_encode(["success" => false, "message" => $error_msg]);
    exit();
}

try {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? trim($input['password']) : '';
    
    if (empty($username) || empty($password)) {
        throw new Exception("Username and password are required");
    }
    
    // Get user from database
    // Check if status column exists, if not, don't filter by status
    $check_status_column = mysqli_query($connection, "SHOW COLUMNS FROM users LIKE 'status'");
    $has_status_column = ($check_status_column && mysqli_num_rows($check_status_column) > 0);
    
    if ($has_status_column) {
        $sql = "SELECT u.*, b.branch_name, b.branch_code 
                FROM users u
                LEFT JOIN branches b ON u.branch_id = b.branch_id
                WHERE u.username = ? AND (u.status = 'Active' OR u.status IS NULL OR u.status = '')";
    } else {
        $sql = "SELECT u.*, b.branch_name, b.branch_code 
                FROM users u
                LEFT JOIN branches b ON u.branch_id = b.branch_id
                WHERE u.username = ?";
    }
    
    $stmt = mysqli_prepare($connection, $sql);
    
    if (!$stmt) {
        $error = mysqli_error($connection);
        error_log("Login SQL Error: " . $error);
        throw new Exception("Error preparing statement: " . $error);
    }
    
    mysqli_stmt_bind_param($stmt, "s", $username);
    
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        error_log("Login Execute Error: " . $error);
        throw new Exception("Error executing query: " . $error);
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        error_log("Login Result Error: " . $error);
        throw new Exception("Error getting result: " . $error);
    }
    
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$user) {
        error_log("Login failed: User not found for username: " . $username);
        throw new Exception("Invalid username or password");
    }
    
    // Check if user is inactive (if status column exists)
    if ($has_status_column && isset($user['status']) && $user['status'] !== 'Active' && $user['status'] !== '' && $user['status'] !== null) {
        error_log("Login failed: User account is inactive for username: " . $username);
        throw new Exception("Your account is inactive. Please contact administrator.");
    }
    
    // Verify password - Support multiple hash types
    $password_match = false;
    $stored_password = $user['password'] ?? '';
    
    if (empty($stored_password)) {
        error_log("Login failed: No password stored for username: " . $username);
        throw new Exception("Invalid username or password");
    }
    
    // Method 1: Check if password is bcrypt (password_hash) - 60 characters, starts with $2y$ or $2a$
    if (strlen($stored_password) == 60 && (substr($stored_password, 0, 4) == '$2y$' || substr($stored_password, 0, 4) == '$2a$' || substr($stored_password, 0, 4) == '$2b$')) {
        // Bcrypt hash (password_hash)
        $password_match = password_verify($password, $stored_password);
    }
    // Method 2: Check if password is MD5 hash (32 characters)
    else if (strlen($stored_password) == 32 && ctype_xdigit($stored_password)) {
        // MD5 hash
        $password_match = (md5($password) === $stored_password);
    }
    // Method 3: Plain text (for development/testing)
    else {
        // Plain text comparison
        $password_match = ($password === $stored_password);
    }
    
    if (!$password_match) {
        error_log("Login failed: Password mismatch for username: " . $username);
        throw new Exception("Invalid username or password");
    }
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    
    // Update user token
    $update_token_sql = "UPDATE users SET token = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($connection, $update_token_sql);
    mysqli_stmt_bind_param($update_stmt, "si", $token, $user['id']);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    
    // Ensure role is a string, not boolean
    $user_role = $user['role'] ?? 'order_taker';
    
    // If role is somehow boolean true, set default
    if ($user_role === true || $user_role === 1) {
        $user_role = 'order_taker';
    }
    
    // If role is somehow boolean false or empty, set default
    if ($user_role === false || $user_role === 0 || empty($user_role)) {
        $user_role = 'order_taker';
    }
    
    // Ensure it's a string
    $user_role = (string) $user_role;
    
    // Prepare response
    $response = [
        "success" => true,
        "message" => "Login successful",
        "token" => $token,
        "role" => $user_role,
        "branch_id" => $user['branch_id'] ? intval($user['branch_id']) : null,
        "branch_name" => $user['branch_name'] ?? null,
        "branch_code" => $user['branch_code'] ?? null,
        "user" => [
            "id" => intval($user['id']),
            "username" => $user['username'],
            "fullname" => $user['fullname'] || $user['username'],
            "role" => $user_role,
            "branch_id" => $user['branch_id'] ? intval($user['branch_id']) : null,
            "branch_name" => $user['branch_name'] ?? null,
            "branch_code" => $user['branch_code'] ?? null
        ]
    ];
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Login Error: " . $e->getMessage());
    error_log("Login Error Trace: " . $e->getTraceAsString());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(401);
    $error_response = [
        "success" => false,
        "message" => $e->getMessage()
    ];
    
    // Ensure we output valid JSON
    $json_output = json_encode($error_response);
    if ($json_output === false) {
        $json_output = json_encode([
            "success" => false,
            "message" => "Login failed. Please check your credentials."
        ]);
    }
    
    echo $json_output;
    exit();
    
} catch (Error $e) {
    error_log("Login Fatal Error: " . $e->getMessage());
    error_log("Login Fatal Error Trace: " . $e->getTraceAsString());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "An error occurred during login. Please try again."
    ]);
    exit();
}

exit();
?>

