<?php
require_once 'cors_headers.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include your configuration file for database connection
include("config.php");

// Check if the database connection is successful
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit();
}

// Handle form submission and check if POST data exists
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if all necessary POST data is set
    $required_fields = ['title', 'route', 'address', 'balance', 'phoneno', 'shopname', 'status','added_by'];
    $missing_fields = array_filter($required_fields, fn($field) => empty($_POST[$field]));

    if (empty($missing_fields)) {
        // Sanitize input values
        $title = $_POST['title'];
        $route = $_POST['route'];
        $address = $_POST['address'];
        $balance = $_POST['balance'];
        $phoneno = $_POST['phoneno'];
        $shopname = $_POST['shopname'];
        $status = $_POST['status'];
        $addedby = $_POST['added_by'];
        // Check if shopname already exists
        $check = $conn->prepare("SELECT id FROM shops WHERE shopname = ?");
        if (!$check) {
            echo json_encode([
                "status" => "error",
                "message" => "Error preparing statement: " . $conn->error
            ]);
            exit();
        }
        $check->bind_param("s", $shopname);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            echo json_encode([
                "status" => "error",
                "message" => "Shopname already exists."
            ]);
        } else {
            // Insert the shop details into the database
            $stmt = $conn->prepare("INSERT INTO shops (title, route, address, balance, phoneno, shopname, status,added_by) VALUES (?, ?, ?, ?, ?, ?, ?,?)");
            if (!$stmt) {
                echo json_encode([
                    "status" => "error",
                    "message" => "Error preparing insert statement: " . $conn->error
                ]);
                exit();
            }
            $stmt->bind_param("sssssss", $title, $route, $address, $balance, $phoneno, $shopname, $status);

            if ($stmt->execute()) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Shop registered successfully!",
                    "data" => [
                        "id" => $stmt->insert_id,
                        "title" => $title,
                        "route" => $route,
                        "address" => $address,
                        "balance" => $balance,
                        "phoneno" => $phoneno,
                        "shopname" => $shopname,
                        "status" => $status
                    ]
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Error: " . $stmt->error
                ]);
            }

            $stmt->close();
        }

        $check->close();
    } else {
        // Handle missing POST data
        echo json_encode([
            "status" => "error",
            "message" => "Please fill out all fields: " . implode(', ', $missing_fields)
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
}

// Close the connection
$conn->close();
?>
