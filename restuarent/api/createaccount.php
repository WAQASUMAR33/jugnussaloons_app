<?php
require_once 'cors_headers.php';
/**
 * Create Account API
 * Handles CRUD operations for user accounts
 * Supports both JSON and form data
 */

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your configuration file for database connection
include("config.php");

// Get input data - handle both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST; // Fallback to form data
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve input data
    $id = $input['id'] ?? ($_POST['id'] ?? '');
    $username = $input['username'] ?? ($_POST['username'] ?? '');
    $password = $input['password'] ?? ($_POST['password'] ?? '');
    $fullname = $input['fullname'] ?? ($_POST['fullname'] ?? '');
    $token = $input['token'] ?? ($_POST['token'] ?? generateToken()); // Generate token if not provided
    $role = $input['role'] ?? ($_POST['role'] ?? 'order_taker');
    $branch_id = isset($input['branch_id']) ? (empty($input['branch_id']) ? null : intval($input['branch_id'])) : (isset($_POST['branch_id']) ? (empty($_POST['branch_id']) ? null : intval($_POST['branch_id'])) : null);
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : (isset($_POST['terminal']) ? intval($_POST['terminal']) : 1);
    $status = $input['status'] ?? ($_POST['status'] ?? 'Active');

    // Validate required fields for new users
    if (empty($id)) {
        if (empty($username) || empty($password) || empty($fullname)) {
            echo json_encode(["success" => false, "message" => "Username, password, and fullname are required."]);
            exit;
        }
    }
    
    // Validate branch_id for non-super_admin roles
    if ($role !== 'super_admin' && (empty($branch_id) || $branch_id <= 0)) {
        echo json_encode(["success" => false, "message" => "Branch ID is required for this role."]);
        exit;
    }
    
    // Validate branch exists (if provided)
    if ($branch_id && $branch_id > 0) {
        $check_branch = "SELECT branch_id FROM branches WHERE branch_id = ?";
        $check_stmt = $conn->prepare($check_branch);
        $check_stmt->bind_param("i", $branch_id);
        $check_stmt->execute();
        $branch_result = $check_stmt->get_result();
        if ($branch_result->num_rows == 0) {
            $check_stmt->close();
            echo json_encode(["success" => false, "message" => "Invalid branch_id. Branch does not exist."]);
            exit;
        }
        $check_stmt->close();
    }

    // Hash the password if it's provided
    $hashed_password = $password ? password_hash($password, PASSWORD_BCRYPT) : null;

    if (empty($id)) {
        // Insert query - Include branch_id
        $sql = "INSERT INTO users (username, password, fullname, token, created_at, updated_at, role, branch_id, status, terminal) 
                VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssi",
            $username,
            $hashed_password,
            $fullname,
            $token,
            $role,
            $branch_id,
            $status,
            $terminal
        );
    } else {
        // Update query
        if ($hashed_password) {
            // Update with password
            $sql = "UPDATE users SET username = ?, password = ?, fullname = ?, token = ?, updated_at = NOW(), role = ?, branch_id = ?, status = ?, terminal = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssssssii",
                $username,
                $hashed_password,
                $fullname,
                $token,
                $role,
                $branch_id,
                $status,
                $terminal,
                $id
            );
        } else {
            // Update without password
            $sql = "UPDATE users SET username = ?, fullname = ?, token = ?, updated_at = NOW(), role = ?, branch_id = ?, status = ?, terminal = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssii",
                $username,
                $fullname,
                $token,
                $role,
                $branch_id,
                $status,
                $terminal,
                $id
            );
        }
    }

    // Execute the query
    if ($stmt->execute()) {
        $message = empty($id) ? "User added successfully." : "User updated successfully.";
        echo json_encode(["success" => true, "message" => $message]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

// Close the connection
$conn->close();
?>
