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
        $sql = "SELECT * FROM orderdetails WHERE orderid = '$id'";
        
        // Execute the query
        $result = mysqli_query($connection, $sql);

        if (!$result) {
            throw new Exception("Error in executing query: " . mysqli_error($connection));
        }

        // Check if the record exists
        $invoiceArray = array();

        // Fetch the result and store it in the array
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $invoiceArray[] = $row;
            }
            // Return the JSON-encoded result
            echo json_encode($invoiceArray);
        } else {
            // If no record is found, return a message
            echo json_encode(['status' => 'error', 'message' => 'No Bills found within the given date range']);
        }


    } catch (Exception $e) {
        // Return error response with the exception message
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    // Close the database connection
    mysqli_close($connection);
?>
