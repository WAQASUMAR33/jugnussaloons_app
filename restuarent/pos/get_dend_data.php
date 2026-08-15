<?php
require_once '../api/cors_headers.php';
include("config.php");

// Content-Type is already set by cors_headers.php, but we can override if needed
// header("Content-Type: application/json");

// Query to get the sum of net_total_amount from orders table where sts = 0, grouped by payment type
$orderQuery = "
    SELECT 
        payment_mode, 
        SUM(net_total_amount) AS total_net_amount 
    FROM orders 
    WHERE sts = 0 and order_status = 'Complete'
    GROUP BY payment_mode
";

// Execute the query for the orders table
$orderResult = mysqli_query($conn, $orderQuery);
if (!$orderResult) {
    echo json_encode(["error" => "Error fetching orders data: " . mysqli_error($conn)]);
    exit();
}

// Fetch order data
$orderData = [];
while ($row = mysqli_fetch_assoc($orderResult)) {
    $orderData[] = $row;
}

// Query to get the sum of amounts from the expenses table
$expenseQuery = "
    SELECT 
        SUM(amount) AS total_expenses 
    FROM expenses
";

// Execute the query for the expense table
$expenseResult = mysqli_query($conn, $expenseQuery);
if (!$expenseResult) {
    echo json_encode(["error" => "Error fetching expenses data: " . mysqli_error($conn)]);
    exit();
}

// Fetch expense data
$expenseData = mysqli_fetch_assoc($expenseResult);

// Combine both results into one response
$response = [
    'orders' => $orderData, // Sum of net_total_amount by payment mode
    'total_expenses' => $expenseData['total_expenses'] ?? 0 // Sum of expenses
];

// Return the response as JSON
echo json_encode($response);

// Close the database connection
mysqli_close($conn);
?>
