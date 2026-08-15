<?php

require_once '../api/cors_headers.php';
include_once "config.php"; // Using include_once prevents accidental double-loading

// Set response headers upfront
header('Content-Type: application/json; charset=utf-8');

// 1. Validate request method immediately before touching anything else
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Invalid request method. Only POST is allowed."]);
    exit;
}

// 2. Validate input parameters
$terminal = $_POST["terminal"] ?? null;

if (empty($terminal)) {
    http_response_code(400); // Bad Request
    echo json_encode(["success" => false, "message" => "Terminal is required."]);
    exit;
}

// 3. Ensure we actually have a working database connection instance
if (!isset($connection) || !$connection) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["success" => false, "message" => "Database connection unavailable."]);
    exit;
}

try {
    // FIX: Re-introduced the WHERE clause to filter by terminal
    $sql = "SELECT * FROM dishes WHERE terminal = ?";
    
    $stmt = $connection->prepare($sql);
    
    // FIX: Ensure statement prepared successfully before calling methods on it
    if ($stmt) {
        $stmt->bind_param("s", $terminal);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $dishes = $result->fetch_all(MYSQLI_ASSOC);

            echo json_encode($dishes);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error executing query."]);
        }

        // Explicitly close statement resource inside the safety check
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to prepare database statement."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An internal error occurred."]);
} finally {
    // ALWAYS close the connection safely at the end of execution
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }
}
?>