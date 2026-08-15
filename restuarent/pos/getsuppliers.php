<?php

    require_once '../api/cors_headers.php';
    include("config.php");

    try {
        // Get the POST parameters 'date1' and 'date2'
        // Modify SQL query to select records from 'invoices' table within the date range
        $sql = "SELECT * FROM suppliers";

        // Execute the query
        $result = mysqli_query($connection, $sql);

        if (!$result) {
            throw new Exception("Error in Selecting: " . mysqli_error($connection));
        }

        // Create an array to hold the response
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
            echo json_encode(['status' => 'error', 'message' => 'No invoices found within the given date range']);
        }
    } catch (Exception $e) {
        // Handle exceptions and return error message
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } finally {
        // Close the database connection
        mysqli_close($connection);
    }

?>
