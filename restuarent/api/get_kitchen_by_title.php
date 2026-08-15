<?php
require_once 'cors_headers.php';
include("config.php");

// Fetch POST data
$terminal_id = $_POST["terminal"];
$title = $_POST["title"];

try {
    // Prepare and execute the SQL query securely using prepared statements
    $stmt = $connection->prepare("SELECT * FROM kitchens WHERE terminal = ? AND title = ?");
    $stmt->bind_param("is", $terminal_id, $title); // "is" specifies the data types: integer and string
    $stmt->execute();
    $result = $stmt->get_result();

    // Create an array to store the results
    $emparray = array();
    while ($row = $result->fetch_assoc()) {
        $emparray[] = $row;
    }

    // Output the results as JSON
    echo json_encode($emparray);

    // Close the statement
    $stmt->close();
} catch (Exception $e) {
    // Handle errors gracefully
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Close the database connection
$connection->close();
?>
