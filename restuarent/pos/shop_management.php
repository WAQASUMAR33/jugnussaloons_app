<?php
    require_once '../api/cors_headers.php';
    include("config.php");

    // Fetching POST data
    $id = $_POST["id"];
    $title = $_POST["title"];
    $route = $_POST["route"];
    $address = $_POST["address"];
    $balance = $_POST["balance"];
    $phoneno = $_POST["phoneno"];
    $shopname = $_POST["shopname"];
    $status = $_POST["status"] ?? 'Active'; // Default to 'Active' if not provided
    $added_by = $_POST["added_by"];

    $current_date = date("Y-m-d H:i:s");

    // Insert or update logic
    if($id == null || $id == "") {
        // Insert new record if no ID provided
        $sql = "INSERT INTO shops (title, route, address, balance, phoneno, shopname, status, created_at, updated_at, added_by) 
                VALUES ('$title', '$route', '$address', '$balance', '$phoneno', '$shopname', '$status', '$current_date', '$current_date', '$added_by')";

        if ($connection->query($sql) === TRUE) {
            echo json_encode(['status' => 'success', 'message' => 'Shop added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $sql . '<br>' . $connection->error]);
        }

    } else {
        // Update existing record if ID provided
        $sql = "UPDATE shops 
                SET title = '$title', route = '$route', address = '$address', balance = '$balance', phoneno = '$phoneno', shopname = '$shopname', 
                status = '$status', updated_at = '$current_date', added_by = '$added_by' 
                WHERE id = '$id'";

        if ($connection->query($sql) === TRUE) {
            echo json_encode(['status' => 'success', 'message' => 'Shop updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $sql . '<br>' . $connection->error]);
        }
    }

    // Closing the connection
    $connection->close();
?>
