<?php
require_once '../api/cors_headers.php';
include("config.php");

// Set the response content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the terminal value from POST data
    $terminal = $_POST["terminal"] ?? null;

    // Validate the terminal input
    if (empty($terminal)) {
        http_response_code(400); // Bad Request
        echo json_encode(["success" => false, "message" => "Terminal is required."]);
        exit;
    }

    // Fixed the hidden character spaces in the SQL string
    $sql = "SELECT dishes.*, categories.name as catname 
            FROM dishes 
            INNER JOIN categories ON categories.category_id = dishes.category_id 
            WHERE dishes.terminal = ?";
    
    if ($stmt = $connection->prepare($sql)) {
        $stmt->bind_param("s", $terminal);

        // Execute the query
        if ($stmt->execute()) {
            $result = $stmt->get_result();

            // Fetch all results into an array
            $emparray = [];
            while ($row = $result->fetch_assoc()) {
                $emparray[] = $row;
            }

            // Return the data as JSON
            echo json_encode($emparray);
        } else {
            // Handle query execution errors
            http_response_code(500); // Internal Server Error
            echo json_encode(["success" => false, "message" => "Error executing query."]);
        }

        // Close the prepared statement
        $stmt->close();
    } else {
        // Handle statement preparation errors
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error: Failed to prepare statement."]);
    }
} else {
    // Handle invalid request methods
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Invalid request method. Only POST is allowed."]);
}

// Safely close the database connection if it exists
if (isset($connection) && $connection) {
    $connection->close();
}
?>