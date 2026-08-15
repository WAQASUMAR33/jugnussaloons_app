<?php
require_once 'cors_headers.php';
    include("config.php");
    
    // Fetching POST data
    $id = $_POST["id"];
    $title = $_POST["title"];
    $rate = $_POST["rate"];
    $current_date = date("Y-m-d H:i:s");

    // Insert or update logic
    if ($id == null || $id == "") {
        // Insert new record if no ID provided
        $sql = "INSERT INTO products (title, rate, created_at, updated_at) 
                VALUES ('$title', '$rate', '$current_date', '$current_date')";

        if ($connection->query($sql) === TRUE) {
            echo json_encode(['status' => 'success', 'message' => 'Product added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $sql . '<br>' . $connection->error]);
        }

    } else {
        // Update existing record if ID provided
        $sql = "UPDATE products 
                SET title = '$title', rate = '$rate', updated_at = '$current_date' 
                WHERE id = '$id'";

        if ($connection->query($sql) === TRUE) {
            echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $sql . '<br>' . $connection->error]);
        }
    }

    // Closing the connection
    $connection->close();
?>
