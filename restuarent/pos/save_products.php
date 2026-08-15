<?php
require_once '../api/cors_headers.php';
include("config.php");

// Fetching POST data
$dish_id = $_POST["dish_id"];  // Assuming this is provided for updating a dish
$category_id = $_POST["category_id"];
$name = $_POST["name"];
$barcode = $_POST["barcode"];
$description = $_POST["description"];
$price = $_POST["price"];
$is_available = $_POST["is_available"];
$is_frequent = $_POST["is_frequent"];
$discount = $_POST["discount"];
$qnty = $_POST["qnty"];

$terminal = $_POST["terminal"];
// Get the current date and time for created_at and updated_at fields if not provided
$current_date = date("Y-m-d H:i:s");

try {
    if ($dish_id == null || $dish_id == "") {
        // Insert new record
        $sql = "INSERT INTO dishes (category_id, name, barcode, description, price, is_available, is_frequent, discount,qnty, terminal, created_at, updated_at) 
                VALUES ('$category_id', '$name', '$barcode', '$description', '$price', '$is_available', '$is_frequent', '$discount','$qnty' ,'$terminal', '$current_date', '$current_date')";

        if ($connection->query($sql) === TRUE) {
            // Return success response in JSON format
            echo json_encode(['status' => 'success', 'message' => 'Dish added successfully']);
        } else {
            throw new Exception('Error: ' . $sql . '<br>' . $connection->error);
        }
    } else {
        // Update existing record
        $sql = "UPDATE dishes 
                SET category_id = '$category_id', name = '$name', barcode = '$barcode', description = '$description', price = '$price', 
                    is_available = '$is_available', is_frequent = '$is_frequent',qnty='$qnty', discount = '$discount', 
                    terminal = '$terminal', updated_at = '$current_date' 
                WHERE dish_id = '$dish_id'";

        if ($connection->query($sql) === TRUE) {
            // Return success response for update
            echo json_encode(['status' => 'success', 'message' => 'Dish updated successfully']);
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
