<?php

/**
 * Get Kitchen Receipt API - Multi-Branch Support
 * Returns receipt data for a specific kitchen and order
 * 
 * GET/POST Parameters:
 * - order_id (int, required) - Order ID
 * - kitchen_id (int, required) - Kitchen ID
 * - branch_id (int, optional) - Branch ID
 */
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["success" => false, "message" => "Fatal error: " . $error['message']]);
        exit();
    }
});

ob_start();

require_once 'cors_headers.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    exit();
}

try {
    include("config.php");
} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}

if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

try {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !is_array($input)) {
        $input = $_GET;
    }
    
    $order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
    $kitchen_id = isset($input['kitchen_id']) ? intval($input['kitchen_id']) : 0;
    $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
    
    if (empty($order_id) || $order_id <= 0) {
        throw new Exception("Order ID is required");
    }
    
    if (empty($kitchen_id) || $kitchen_id <= 0) {
        throw new Exception("Kitchen ID is required");
    }
    
    // Get order details
    $order_sql = "SELECT 
                    o.order_id,
                    o.order_type,
                    o.table_id,
                    o.hall_id,
                    o.comments,
                    o.created_at,
                    t.table_number,
                    h.name as hall_name,
                    k.title as kitchen_name,
                    k.code as kitchen_code
                  FROM orders o
                  LEFT JOIN tables t ON o.table_id = t.table_id
                  LEFT JOIN halls h ON o.hall_id = h.hall_id
                  LEFT JOIN kitchens k ON k.kitchen_id = ?
                  WHERE o.order_id = ?";
    
    $order_stmt = mysqli_prepare($connection, $order_sql);
    mysqli_stmt_bind_param($order_stmt, "ii", $kitchen_id, $order_id);
    mysqli_stmt_execute($order_stmt);
    $order_result = mysqli_stmt_get_result($order_stmt);
    $order = mysqli_fetch_assoc($order_result);
    mysqli_stmt_close($order_stmt);
    
    if (!$order) {
        throw new Exception("Order not found");
    }
    
    // Get items for this kitchen
    // Since order_items_kitchen already has dish_id, we can join directly with dishes
    $items_sql = "SELECT 
                    oik.dish_name,
                    oik.quantity,
                    oik.price,
                    oik.notes,
                    c.name as category_name
                  FROM order_items_kitchen oik
                  LEFT JOIN dishes d ON oik.dish_id = d.dish_id
                  LEFT JOIN categories c ON d.category_id = c.category_id
                  WHERE oik.order_id = ? AND oik.kitchen_id = ?";
    
    if (!empty($branch_id)) {
        $items_sql .= " AND oik.branch_id = ?";
    }
    
    $items_sql .= " ORDER BY oik.created_at ASC";
    
    $items_stmt = mysqli_prepare($connection, $items_sql);
    
    if (!empty($branch_id)) {
        mysqli_stmt_bind_param($items_stmt, "iii", $order_id, $kitchen_id, $branch_id);
    } else {
        mysqli_stmt_bind_param($items_stmt, "ii", $order_id, $kitchen_id);
    }
    
    mysqli_stmt_execute($items_stmt);
    $items_result = mysqli_stmt_get_result($items_stmt);
    
    $items = [];
    $total = 0;
    while ($item = mysqli_fetch_assoc($items_result)) {
        $item_total = floatval($item['price']) * intval($item['quantity']);
        $total += $item_total;
        $items[] = [
            'dish_name' => $item['dish_name'],
            'category_name' => $item['category_name'],
            'quantity' => intval($item['quantity']),
            'price' => floatval($item['price']),
            'total' => $item_total,
            'notes' => $item['notes']
        ];
    }
    mysqli_stmt_close($items_stmt);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => true,
        "data" => [
            "order" => [
                "order_id" => $order['order_id'],
                "order_number" => "ORD-" . $order['order_id'],
                "order_type" => $order['order_type'],
                "table_number" => $order['table_number'] ?? '-',
                "hall_name" => $order['hall_name'] ?? '-',
                "created_at" => $order['created_at'],
                "comments" => $order['comments']
            ],
            "kitchen" => [
                "kitchen_id" => $kitchen_id,
                "kitchen_name" => $order['kitchen_name'],
                "kitchen_code" => $order['kitchen_code']
            ],
            "items" => $items,
            "total" => $total,
            "items_count" => count($items)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get Kitchen Receipt Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
}

exit();
?>
