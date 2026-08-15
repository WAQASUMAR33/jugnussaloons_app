<?php
/**
 * User Management API - Multi-Branch Support
 * Create, update, delete users with branch and role assignment
 * 
 * POST Parameters (JSON):
 * - id (int, optional) - For update, empty for create
 * - username (string, required)
 * - password (string, required for create)
 * - fullname (string, required)
 * - role (string, required) - super_admin, branch_admin, accountant, order_taker, kitchen
 * - branch_id (int, optional) - Required for all roles except super_admin
 * - status (string, optional) - Active/Inactive
 * 
 * DELETE Parameters (JSON):
 * - id (int, required) - User ID to delete
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
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // DELETE - Delete user
    if ($method === 'DELETE') {
        $user_id = isset($input['id']) ? intval($input['id']) : 0;
        
        if (empty($user_id) || $user_id <= 0) {
            throw new Exception("User ID is required for deletion");
        }
        
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error deleting user: " . mysqli_error($connection));
        }
        
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => $affected_rows > 0,
            "message" => $affected_rows > 0 ? "User deleted successfully" : "User not found"
        ]);
        exit();
    }
    
    // POST - Create or Update
    if ($method === 'POST') {
        $user_id = isset($input['id']) ? intval($input['id']) : 0;
        $username = isset($input['username']) ? trim($input['username']) : '';
        $password = isset($input['password']) ? trim($input['password']) : '';
        $fullname = isset($input['fullname']) ? trim($input['fullname']) : '';
        $role = isset($input['role']) ? trim($input['role']) : '';
        $branch_id = isset($input['branch_id']) ? (empty($input['branch_id']) ? null : intval($input['branch_id'])) : null;
        $status = isset($input['status']) ? trim($input['status']) : 'Active';
        
        // Validate required fields
        if (empty($username)) {
            throw new Exception("Username is required");
        }
        
        if (empty($fullname)) {
            throw new Exception("Full name is required");
        }
        
        if (empty($role)) {
            throw new Exception("Role is required");
        }
        
        $valid_roles = ['super_admin', 'branch_admin', 'accountant', 'order_taker', 'kitchen'];
        if (!in_array($role, $valid_roles)) {
            throw new Exception("Invalid role. Must be one of: " . implode(', ', $valid_roles));
        }
        
        // Super admin doesn't need branch_id, others do
        if ($role !== 'super_admin' && (empty($branch_id) || $branch_id <= 0)) {
            throw new Exception("Branch ID is required for this role");
        }
        
        // Validate branch exists (if provided)
        if ($branch_id) {
            $check_branch = "SELECT branch_id FROM branches WHERE branch_id = ?";
            $check_stmt = mysqli_prepare($connection, $check_branch);
            mysqli_stmt_bind_param($check_stmt, "i", $branch_id);
            mysqli_stmt_execute($check_stmt);
            $branch_result = mysqli_stmt_get_result($check_stmt);
            if (mysqli_num_rows($branch_result) == 0) {
                mysqli_stmt_close($check_stmt);
                throw new Exception("Invalid branch_id. Branch does not exist.");
            }
            mysqli_stmt_close($check_stmt);
        }
        
        // Validate status
        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }
        
        if ($user_id > 0) {
            // Update existing user
            // Check if username is unique (excluding current user)
            $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "si", $username, $user_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            if (mysqli_num_rows($result) > 0) {
                mysqli_stmt_close($check_stmt);
                throw new Exception("Username already exists");
            }
            mysqli_stmt_close($check_stmt);
            
            if (!empty($password)) {
                // Update with password
                $hashed_password = md5($password);
                $sql = "UPDATE users SET 
                        username = ?,
                        password = ?,
                        fullname = ?,
                        role = ?,
                        branch_id = ?,
                        status = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssssisi", $username, $hashed_password, $fullname, $role, $branch_id, $status, $user_id);
            } else {
                // Update without password
                $sql = "UPDATE users SET 
                        username = ?,
                        fullname = ?,
                        role = ?,
                        branch_id = ?,
                        status = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "sssisi", $username, $fullname, $role, $branch_id, $status, $user_id);
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error updating user: " . mysqli_error($connection));
            }
            
            mysqli_stmt_close($stmt);
            $message = "User updated successfully";
        } else {
            // Create new user
            if (empty($password)) {
                throw new Exception("Password is required for new users");
            }
            
            // Check if username is unique
            $check_sql = "SELECT id FROM users WHERE username = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $username);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            if (mysqli_num_rows($result) > 0) {
                mysqli_stmt_close($check_stmt);
                throw new Exception("Username already exists");
            }
            mysqli_stmt_close($check_stmt);
            
            $hashed_password = md5($password);
            $sql = "INSERT INTO users (username, password, fullname, role, branch_id, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "ssssis", $username, $hashed_password, $fullname, $role, $branch_id, $status);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error creating user: " . mysqli_error($connection));
            }
            
            $user_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt);
            $message = "User created successfully";
        }
        
        // Get updated user
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
            "message" => $message,
            "data" => $user
        ]);
        exit();
    }
    
    // GET - Get all users or users by branch
    if ($method === 'GET') {
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
        $role = isset($_GET['role']) ? trim($_GET['role']) : '';
        
        if ($branch_id > 0) {
            // Get users for specific branch
            if (!empty($role)) {
                $sql = "SELECT u.*, b.branch_name, b.branch_code 
                        FROM users u
                        LEFT JOIN branches b ON u.branch_id = b.branch_id
                        WHERE u.branch_id = ? AND u.role = ?
                        ORDER BY u.fullname ASC";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "is", $branch_id, $role);
            } else {
                $sql = "SELECT u.*, b.branch_name, b.branch_code 
                        FROM users u
                        LEFT JOIN branches b ON u.branch_id = b.branch_id
                        WHERE u.branch_id = ?
                        ORDER BY u.fullname ASC";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "i", $branch_id);
            }
        } else {
            // Get all users
            if (!empty($role)) {
                $sql = "SELECT u.*, b.branch_name, b.branch_code 
                        FROM users u
                        LEFT JOIN branches b ON u.branch_id = b.branch_id
                        WHERE u.role = ?
                        ORDER BY u.fullname ASC";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "s", $role);
            } else {
                $sql = "SELECT u.*, b.branch_name, b.branch_code 
                        FROM users u
                        LEFT JOIN branches b ON u.branch_id = b.branch_id
                        ORDER BY u.fullname ASC";
                $stmt = mysqli_prepare($connection, $sql);
            }
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            unset($row['password']); // Remove password
            $users[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "data" => $users
        ]);
        exit();
    }
    
    throw new Exception("Invalid request method");
    
} catch (Exception $e) {
    error_log("User Management Error: " . $e->getMessage());
    
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

