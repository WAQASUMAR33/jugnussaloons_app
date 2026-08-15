<?php
require_once '../api/cors_headers.php';
include("config.php");

// Fetching POST data
$id = $_POST["id"];  // Assuming this is provided for updating a customer
$name = $_POST["name"];
$mobileNo = $_POST["mobileNo"];
$address = $_POST["address"];
$accountNo = $_POST["accountNo"];
$balance = $_POST["balance"];
$cardtype = $_POST["cardtype"];
$terminal = $_POST["terminal"];
$created_at = $_POST["created_at"];
$updated_at = $_POST["updated_at"];

// Get the current date and time for updated_at field if not provided
$current_date = date("Y-m-d H:i:s");

try {
    if ($id == null || $id == "") {
        // Insert new record into the customers table
        $sql = "INSERT INTO customers (name, mobileNo, address, accountNo, balance, cardtype, terminal, created_at, updated_at) 
                VALUES ('$name', '$mobileNo', '$address', '$accountNo', '$balance', '$cardtype', '$terminal', '$current_date', '$current_date')";

        if ($connection->query($sql) === TRUE) {
            // Return success response in JSON format
            echo json_encode(['status' => 'success', 'message' => 'Customer added successfully']);
        } else {
            throw new Exception('Error: ' . $sql . '<br>' . $connection->error);
        }
    } else {
        // Update existing record in the customers table
        $sql = "UPDATE customers 
                SET name = '$name', mobileNo = '$mobileNo', address = '$address', accountNo = '$accountNo', balance = '$balance', cardtype = '$cardtype', terminal = '$terminal', updated_at = '$current_date' 
                WHERE id = '$id'";

        if ($connection->query($sql) === TRUE) {
            // Return success response for update
            echo json_encode(['status' => 'success', 'message' => 'Customer updated successfully']);
        } else {
            throw new Exception('Error: ' . $sql . '<br>' . $connection->error);
        }
    }
} catch (Exception $e) {
    // Return error response with the exception message
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Closing the database connection
$connection->close();
?>
