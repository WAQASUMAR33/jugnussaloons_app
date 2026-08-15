<?php
require_once 'cors_headers.php';
// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your configuration file for database connection
include("config.php");

// Function to sanitize user input
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Handle POST request to delete a record by id
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the action is 'delete' and id is provided
    if (isset($_POST['id'])) {
        $id = sanitizeInput($_POST['id']);

        // Prepare the SQL statement to delete the record
        $stmt = $connection->prepare("DELETE FROM routes WHERE id = ?");
        if (!$stmt) {
            die(json_encode(["status" => "error", "message" => "Error preparing statement: " . $connection->error]));
        }

        // Bind the id parameter to the SQL query
        $stmt->bind_param("i", $id);

        // Execute the query
        if ($stmt->execute()) {
            // Check if any rows were affected (i.e., if the record was deleted)
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "Record deleted successfully."]);
            } else {
                echo json_encode(["status" => "error", "message" => "No record found with the given id."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Error deleting record: " . $stmt->error]);
        }

        // Close the statement
        $stmt->close();
    } else {
        // Handle missing id
        echo json_encode(["status" => "error", "message" => "Please provide an id."]);
    }
} else {
    // Handle invalid request method
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

// Close the connection
$connection->close();
?>
