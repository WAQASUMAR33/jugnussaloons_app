<?php
require_once '../api/cors_headers.php';
include_once "config.php"; // Safeguard connection memory footprints

// 1. Enforce POST request method for data destruction
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method. Only POST is allowed."]);
    exit;
}

// 2. Fetch and check the Supplier ID cleanly
$sup_id = $_POST["id"] ?? null;

if (empty($sup_id)) {
    echo json_encode(["status" => "error", "message" => "Supplier ID not provided."]);
    exit;
}

// 3. Ensure database connection layer is active
if (!$connection) {
    echo json_encode(["status" => "error", "message" => "Database connection offline."]);
    exit;
}

try {
    // 4. Secure Prepared Statement to neutralize SQL Injection completely
    $sql = "DELETE FROM suppliers WHERE id = ?";
    
    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare deletion statement: " . $connection->error);
    }

    // Bind parameter safely ('i' stands for integer)
    $stmt->bind_param("i", $sup_id);
    
    if ($stmt->execute()) {
        // Verify if a supplier record was actually found and dropped
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Supplier record deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No supplier found with the provided ID.']);
        }
    } else {
        throw new Exception("Execution failed: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // 5. Explicitly free the thread socket to protect your hourly resource limits
    if ($connection instanceof mysqli) {
        $connection->close();
    } else if ($connection) {
        mysqli_close($connection);
    }
}
?>