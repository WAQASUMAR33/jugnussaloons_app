<?php
require_once '../api/cors_headers.php';
include("config.php");

// Fetching POST data
$id = $_POST["id"];  // Assuming this is provided for updating a supplier
$name = $_POST["name"];
$mobileNo = $_POST["mobileNo"];
$accountNo = $_POST["accountNo"];
$balance = $_POST["balance"];
$bname = $_POST["bname"];
$bankname = $_POST["bankname"];
$address = $_POST["address"];
$terminal = $_POST["terminal"];
$created_at = $_POST["created_at"];
$updated_at = $_POST["updated_at"];

// Get the current date and time for created_at and updated_at fields if not provided
$current_date = date("Y-m-d H:i:s");

try {
    if($id == null || $id == "") {
        // Insert new record into the suppliers table
        $sql = "INSERT INTO suppliers (name, mobileNo, accountNo, balance, bname, bankname, address, created_at, updated_at,terminal) 
                VALUES ('$name', '$mobileNo', '$accountNo', '$balance', '$bname', '$bankname', '$address', '$current_date', '$current_date','$terminal')";

        if ($connection->query($sql) === TRUE) {
            // Return success response in JSON format
            echo json_encode(['status' => 'success', 'message' => 'Supplier added successfully']);
        } else {
            throw new Exception('Error: ' . $sql . '<br>' . $connection->error);
        }
    } else {
        // Update existing record in the suppliers table
        $sql = "UPDATE suppliers 
                SET name = '$name', mobileNo = '$mobileNo', accountNo = '$accountNo', balance = '$balance', bname = '$bname', bankname = '$bankname', address = '$address', updated_at = '$current_date' 
                WHERE id = '$id'";

        if ($connection->query($sql) === TRUE) {
            // Return success response for update
            echo json_encode(['status' => 'success', 'message' => 'Supplier updated successfully']);
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
