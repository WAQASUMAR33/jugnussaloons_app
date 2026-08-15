<?php
require_once '../api/cors_headers.php';
include("config.php");

// Get the table_id from the query parameters
$order_id = isset($_POST["table_id"]) ? $_POST["table_id"] : null;

if (!$order_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Table ID is required.',
    ]);
    exit;
}

try {
    // Fetch the order record based on table_id
    $orderQuery = "SELECT orders.*,halls.name as hall_name , tables.table_number as table_no FROM orders LEFT join tables on orders.table_id = tables.table_id left join halls on halls.hall_id = tables.hall_id WHERE orders.table_id = '$order_id' and tables.status ='Running'  and (orders.order_status= 'Running'  or  orders.order_status = 'Generate Bill')";
    $orderResult = $connection->query($orderQuery);

    if ($orderResult->num_rows > 0) {
        $order = $orderResult->fetch_assoc();

        // Fetch order details for the given order
        $detailsQuery = "SELECT order_items.*,dishes.name FROM order_items inner join dishes on dishes.dish_id   = order_items.dish_id  WHERE order_items.order_id = '" . $order['order_id'] . "' and order_items.is_cancel =0";
        $detailsResult = $connection->query($detailsQuery);

        $orderDetails = [];
        while ($row = $detailsResult->fetch_assoc()) {
            $orderDetails[] = $row;
        }

        // Add order details to the order response
        $order['order_details'] = $orderDetails;

        // Return success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Order fetched successfully.',
            'order' => $order,
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No order found for the given Table ID.',
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}

// Close the database connection
$connection->close();
?>
