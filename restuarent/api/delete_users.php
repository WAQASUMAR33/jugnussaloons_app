<?php
require_once 'cors_headers.php';
/**
 * Delete Users API
 * Handles deletion of user accounts
 * Supports both JSON and form data
 */

// Enable detailed error reporting
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

// Handle POST request to delete a record by id
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the action is 'delete' and id is provided
    $id = $input['id'] ?? ($_POST['id'] ?? '');
    
    if (empty($id)) {
        echo json_encode(["success" => false, "message" => "Please provide an id."]);
        exit();
    }

    // Prepare the SQL statement to delete the record
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Error preparing statement: " . $conn->error]);
        exit();
    }

    // Bind the id parameter to the SQL query
    $stmt->bind_param("i", $id);

    // Execute the query
    if ($stmt->execute()) {
        // Check if any rows were affected (i.e., if the record was deleted)
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "User deleted successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "No user found with the given id."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Error deleting user: " . $stmt->error]);
    }

    // Close the statement
    $stmt->close();
} else {
    // Handle invalid request method
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

// Close the connection
$conn->close();
?>
