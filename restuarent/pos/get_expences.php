<?php
require_once '../api/cors_headers.php';
include_once "config.php"; // Safeguard connection resources

// 1. Enforce POST request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method. Only POST is allowed."]);
    exit;
}

// 2. Fetch and check the ID cleanly
$customer_id = $_POST["id"] ?? null;

if (empty($customer_id)) {
    echo json_encode(["status" => "error", "message" => "Customer ID is required."]);
    exit;
}

// 3. Ensure database connection layer is active
if (!$connection) {
    echo json_encode(["status" => "error", "message" => "Database connection offline."]);
    exit;
}

try {
    // 4. Secure Prepared Statement
    // NOTE: Double check your ON clause! If custrnx uses customer_id, change 'supid' to 'customer_id'
    $sql = "SELECT customers.*, custrnx.* FROM customers 
            INNER JOIN custrnx ON customers.id = custrnx.supid 
            WHERE customers.id = ? 
            ORDER BY custrnx.id DESC";

    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        throw new Exception("Statement preparation failed: " . mysqli_error($connection));
    }

    // Bind the customer ID safely ('i' for integer)
    mysqli_stmt_bind_param($stmt, "i", $customer_id);
    
    // Execute the query
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $invoiceArray = array();

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $invoiceArray[] = $row;
            }
            // Return the ledger list data array
            echo json_encode($invoiceArray);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No Ledger Found']);
        }
    } else {
        throw new Exception("Execution failed: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // 5. Safely drop connection context to protect max_connections hourly limits
    if ($connection) {
        mysqli_close($connection);
    }
}
?>