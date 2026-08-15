<?php

    require_once '../api/cors_headers.php';
    include("config.php");

    try {
        // Fetching the ID from POST data
        $id = $_POST["id"] ?? '';

        // Check if ID is provided
        if (empty($id)) {
            throw new Exception("ID is required.");
        }

        // Prepare the SQL statement
        $sql = "SELECT * FROM suppliers WHERE id = '$id'";
        
        // Execute the query
        $result = mysqli_query($connection, $sql);

        if (!$result) {
            throw new Exception("Error in executing query: " . mysqli_error($connection));
        }

        // Check if the record exists
        if (mysqli_num_rows($result) > 0) {
            // Fetch the record
            $row = mysqli_fetch_assoc($result);
            // Return the record as JSON
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            // Return a message if no record is found
            echo json_encode(['status' => 'error', 'message' => 'No record found for the provided ID.']);
        }

    } catch (Exception $e) {
        // Return error response with the exception message
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    // Close the database connection
    mysqli_close($connection);
?>
