<?php
require_once 'cors_headers.php';
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your configuration file for database connection
include("config.php");

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve input data
    $hall_id = $_POST['hall_id'] ?? '';
    $terminal_id = $_POST['terminal_id'] ?? '';

    if (empty($hall_id) || empty($terminal_id)) {
        echo json_encode(["success" => false, "message" => "Hall ID and Terminal ID are required."]);
        exit;
    }

    // Query to delete a hall based on hall_id and terminal_id
    $sql = "DELETE FROM halls WHERE hall_id = ? AND terminal = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $hall_id, $terminal_id);

    // Execute the query
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Hall deleted successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "No hall found with the provided Hall ID and Terminal ID."]);
        }
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
