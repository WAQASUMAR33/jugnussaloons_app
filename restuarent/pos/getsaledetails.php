<?php
/**
 * Get Sales Details API (GET Version)
 * Returns sales data grouped by dish_id with quantities and totals
 * 
 * GET Parameters:
 * - branch_id: (required) Branch ID
 * - sts: (required) Order status
 */

require_once 'pos_init.php';

try {
    // Read GET parameters
    $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
    $sts = isset($_GET['sts']) ? trim($_GET['sts']) : null;

    // Validate inputs
    if ($branch_id <= 0) {
        throw new Exception("branch_id is required and must be greater than 0");
    }

    if ($sts === null || $sts === '') {
        throw new Exception("sts is required");
    }

    // Check if order_items table exists
    $check_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
    $has_order_items = ($check_table && mysqli_num_rows($check_table) > 0);

    if ($has_order_items) {
        // Use order_items table
        $sql = "SELECT 
                    dishes.dish_id, 
                    MAX(order_items.updated_at) AS updated_at, 
                    MAX(order_items.item_id) AS item_id, 
                    MAX(kitchens.title) AS title, 
                    MAX(dishes.name) AS name, 
                    MAX(categories.name) AS catname, 
                    MAX(orders.order_id) AS order_id, 
                    MAX(orders.table_id) AS table_id, 
                    MAX(orders.order_taker_id) AS order_taker_id, 
                    SUM(CASE WHEN order_items.is_cancel = 0 THEN order_items.quantity ELSE 0 END) AS tnc, 
                    SUM(CASE WHEN order_items.is_cancel = 1 THEN order_items.quantity ELSE 0 END) AS tc, 
                    SUM(CASE WHEN order_items.is_cancel = 0 THEN order_items.total_amount ELSE 0 END) AS tnc_total, 
                    SUM(CASE WHEN order_items.is_cancel = 1 THEN order_items.total_amount ELSE 0 END) AS tc_total, 
                    MAX(order_items.price) AS price, 
                    MAX(order_items.created_at) AS created_at 
                FROM orders  
                INNER JOIN order_items ON orders.order_id = order_items.order_id   
                INNER JOIN dishes ON dishes.dish_id = order_items.dish_id 
                INNER JOIN categories ON dishes.category_id = categories.category_id 
                INNER JOIN kitchens ON kitchens.kitchen_id = categories.kid 
                WHERE 1=1";

        $params = [];
        $types = '';

        $sql .= " AND orders.branch_id = ?";
        $types .= 'i';
        $params[] = $branch_id;

        $sql .= " AND orders.sts = ?";
        $types .= 's';
        $params[] = $sts;

        $sql .= " GROUP BY dishes.dish_id ORDER BY tnc DESC";

    } else {
        // Legacy table orderdetails
        $sql = "SELECT 
                    dishes.dish_id, 
                    MAX(orderdetails.updated_at) AS updated_at, 
                    MAX(orderdetails.id) AS item_id, 
                    MAX(kitchens.title) AS title, 
                    MAX(dishes.name) AS name, 
                    MAX(categories.name) AS catname, 
                    MAX(orders.order_id) AS order_id, 
                    MAX(orders.table_id) AS table_id, 
                    MAX(orders.order_taker_id) AS order_taker_id, 
                    SUM(CASE WHEN orderdetails.is_cancel = 0 THEN orderdetails.quantity ELSE 0 END) AS tnc, 
                    SUM(CASE WHEN orderdetails.is_cancel = 1 THEN orderdetails.quantity ELSE 0 END) AS tc, 
                    SUM(CASE WHEN orderdetails.is_cancel = 0 THEN orderdetails.total_amount ELSE 0 END) AS tnc_total, 
                    SUM(CASE WHEN orderdetails.is_cancel = 1 THEN orderdetails.total_amount ELSE 0 END) AS tc_total, 
                    MAX(orderdetails.price) AS price, 
                    MAX(orderdetails.created_at) AS created_at 
                FROM orders  
                INNER JOIN orderdetails ON orders.order_id = CAST(SUBSTRING(orderdetails.orderid, 5) AS UNSIGNED)
                INNER JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                INNER JOIN categories ON dishes.category_id = categories.category_id 
                INNER JOIN kitchens ON kitchens.kitchen_id = categories.kid 
                WHERE 1=1";

        $params = [];
        $types = '';

        $sql .= " AND orders.branch_id = ?";
        $types .= 'i';
        $params[] = $branch_id;

        $sql .= " AND orders.sts = ?";
        $types .= 's';
        $params[] = $sts;

        $sql .= " GROUP BY dishes.dish_id ORDER BY tnc DESC";
    }

    // Prepare and execute query
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error executing query: " . mysqli_error($connection));
    }

    // Fetch results
    $result = mysqli_stmt_get_result($stmt);
    $invoiceArray = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $invoiceArray[] = $row;
    }

    mysqli_stmt_close($stmt);

    // Output response
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(200);
    echo json_encode($invoiceArray);
    exit();

} catch (Exception $e) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
    exit();
}

?>
