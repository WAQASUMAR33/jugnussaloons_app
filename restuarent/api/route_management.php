<?php
require_once 'cors_headers.php';
    include("config.php");
    
    // Fetching POST data
    $id = $_POST["id"];
    $name = $_POST["name"];
    $terminal = $_POST["terminal"];
    $description = $_POST["description"];

    $current_date = date("Y-m-d H:i:s");

    // Insert or update logic
    if($id == null || $id == "") {
        // Insert new record if no ID provided
        $sql = "INSERT INTO routes (name, description,terminal, created_at, updated_at) 
                VALUES ('$name', '$description','$terminal','$current_date', '$current_date')";

        if ($connection->query($sql) === TRUE) {
            echo json_encode(['status' => 'success', 'message' => 'Route added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $sql . '<br>' . $connection->error]);
        }

    } else {
        // Update existing record if ID provided
        $sql = "UPDATE routes 
                SET name = '$name', description = '$description', updated_at = '$current_date' 
                WHERE id = '$id'";

        if ($connection->query($sql) === TRUE) {
            echo json_encode(['status' => 'success', 'message' => 'Route updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $sql . '<br>' . $connection->error]);
        }
    }

    // Closing the connection
    $connection->close();
?>
