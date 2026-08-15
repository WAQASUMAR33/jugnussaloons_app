<?php

require_once '../api/cors_headers.php';
include("config.php");

// Set the response content type to JSON upfront
header('Content-Type: application/json; charset=utf-8');

// 1. Enforce POST request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["status" => "error", "message" => "Invalid request method. Only POST is allowed."]);
    exit;
}

try {
    // Fetching the ID from POST data
    $id = $_POST["id"] ?? '';

    // Check if ID is provided
    if (empty($id)) {
        http_response_code(400); // Bad Request
        throw new Exception("ID is required.");
    }

    // 2. FIX: Use a prepared statement to prevent SQL Injection
    $sql = "SELECT * FROM purdetails WHERE invoiceid = ?";
    
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        error_log("SQL Prepare Error: " . mysqli_error($connection));
        http_response_code(500);
        throw new Exception("Internal server error during query setup.");
    }

    // Bind parameters and execute
    mysqli_stmt_bind_param($stmt, "s", $id);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("SQL Execution Error: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        http_response_code(500);
        throw new Exception("Internal server error during data retrieval.");
    }

    $result = mysqli_stmt_get_result($stmt);
    $invoiceArray = array();

    // Fetch the results
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $invoiceArray[] = $row;
        }
        
        http_response_code(200);
        echo json_encode($invoiceArray);
    } else {
        // 3. FIX: Corrected error message to match ID searching behavior
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'No invoices found matching the provided ID.']);
    }

    // Close statement resource
    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    // Return error response with the exception message
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // 4. FIX: Safely close connection inside a finally block to ensure it always fires
    if (isset($connection) && $connection) {
        mysqli_close($connection);
    }
}
?>