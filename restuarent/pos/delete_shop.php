<?php
require_once '../api/cors_headers.php';
include_once "config.php"; // Safeguard connection resources

// 1. Enforce POST request method for destructive actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method. Only POST is allowed."]);
    exit;
}

// 2. Fetch and check the Shop ID cleanly
$shop_id = $_POST["id"] ?? null;

if (empty($shop_id)) {
    echo json_encode(["status" => "error", "message" => "Shop ID not provided."]);
    exit;
}

// 3. Ensure database connection layer is initialized
if (!$connection) {
    echo json_encode(["status" => "error", "message" => "Database connection offline."]);
    exit;
}

try {
    // 4. Secure Prepared Statement to stop SQL Injection
    $sql = "DELETE FROM shops WHERE id = ?";
    
    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $connection->error);
    }

    // Bind parameters securely ('i' stands for integer)
    $stmt->bind_param("i", $shop_id);
    
    if ($stmt->execute()) {
        // Verify if a row actually matched and dropped
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Shop record deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No shop found with the provided ID.']);
        }
    } else {
        throw new Exception("Execution failed: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // 5. Explicitly drop thread allocation context to protect max_connections hourly limits
    if ($connection instanceof mysqli) {
        $connection->close();
    } else if ($connection) {
        mysqli_close($connection);
    }
}
?>