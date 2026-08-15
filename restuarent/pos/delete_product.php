<?php
require_once '../api/cors_headers.php';
include_once "config.php"; // Use include_once to protect resources

// 1. Enforce POST request method for destructive actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method. Only POST is allowed."]);
    exit;
}

// 2. Fetch and check the ID
$target_id = $_POST["id"] ?? null;

if (empty($target_id)) {
    echo json_encode(["status" => "error", "message" => "Target ID not provided."]);
    exit;
}

// 3. Ensure database connection is active
if (!$connection) {
    echo json_encode(["status" => "error", "message" => "Database connection offline."]);
    exit;
}

try {
    /* 👉 OPTION A: If you want to delete a DISH/MENU ITEM, use this query:
       $sql = "DELETE FROM dishes WHERE dish_id = ?";
       
       👉 OPTION B: If you want to delete a SUPPLIER, use this query:
       $sql = "DELETE FROM suppliers WHERE id = ?";
    */
    
    // Adjust this string to match your intended table target (Currently set to dishes based on your original query)
    $sql = "DELETE FROM dishes WHERE dish_id = ?";
    
    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $connection->error);
    }

    // Bind parameter securely ('i' stands for integer)
    $stmt->bind_param("i", $target_id);
    
    if ($stmt->execute()) {
        // Check if a row was actually deleted or if the ID didn't exist
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Record deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No record found with the provided ID.']);
        }
    } else {
        throw new Exception("Execution failed: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // 4. Safely terminate the connection to prevent hitting max_connections limits
    if ($connection instanceof mysqli) {
        $connection->close();
    } else if ($connection) {
        mysqli_close($connection);
    }
}
?>