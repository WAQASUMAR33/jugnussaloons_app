<?php
include("config.php");

// Set headers to allow cross-origin requests and define allowed HTTP methods
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Fetching POST data
$order_id = $_POST["order_id"] ?? null;
$new_table_id = $_POST["table_id"] ?? null;

// Validate input
if (!$order_id || !$new_table_id) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID and New Table ID are required']);
    exit;
}

$current_date = date("Y-m-d H:i:s");

try {
    // Step 1: Get the current table_id for the given order_id
    $query = "SELECT table_id FROM orders WHERE order_id = '$order_id'";
    $result = $connection->query($query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $old_table_id = $row['table_id'];
        
        // Step 2: Update table_id in the orders table
        $update_order = "UPDATE orders SET table_id = '$new_table_id', updated_at = '$current_date' WHERE order_id = '$order_id'";
        if (!$connection->query($update_order)) {
            throw new Exception('Error updating order table_id: ' . $connection->error);
        }

        // Step 3: Set the previous table's status to "Available"
        if ($old_table_id) {
            $update_old_table = "UPDATE tables SET status = 'Available' WHERE table_id = '$old_table_id'";
            if (!$connection->query($update_old_table)) {
                throw new Exception('Error updating previous table status: ' . $connection->error);
            }
        }

        // Step 4: Set the new table's status to "Running"
        $update_new_table = "UPDATE tables SET status = 'Running' WHERE table_id = '$new_table_id'";
        if (!$connection->query($update_new_table)) {
            throw new Exception('Error updating new table status: ' . $connection->error);
        }

        echo json_encode(['status' => 'success', 'message' => 'Table transferred successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Closing the database connection
$connection->close();
?>
