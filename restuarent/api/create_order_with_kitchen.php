<?php

/**
 * Create Order with Kitchen Routing API
 * Creates order and routes items to appropriate kitchens based on category kitchen_id
 * 
 * POST Parameters (JSON):
 * - order_type (string, required) - Dine In, Take Away, Delivery
 * - hall_id (int, optional) - For Dine In orders
 * - table_id (int, optional) - For Dine In orders
 * - items (array, required) - Array of {dish_id, price, quantity}
 * - comments (string, optional) - Order comments
 * - terminal (int, required) - Terminal number
 * - branch_id (int, required) - Branch ID
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
        $input = $_POST;
    }
    
    // Validate required fields
    if (empty($input['order_type'])) {
        throw new Exception("Order type is required");
    }
    
    if (empty($input['items']) || !is_array($input['items']) || count($input['items']) == 0) {
        throw new Exception("Order items are required");
    }
    
    $order_type = $input['order_type'];
    $hall_id = isset($input['hall_id']) ? intval($input['hall_id']) : 0;
    $table_id = isset($input['table_id']) ? intval($input['table_id']) : 0;
    $items = $input['items'];
    $comments = isset($input['comments']) ? trim($input['comments']) : '';
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    $order_taker_id = isset($input['order_taker_id']) ? intval($input['order_taker_id']) : 1;
    $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
    
    if (empty($branch_id) || $branch_id <= 0) {
        // Fallback to terminal if branch_id not provided
        $branch_id = $terminal;
    }
    
    // Calculate total amounts before creating order
    $g_total_amount = 0;
    foreach ($items as $item) {
        $item_price = floatval($item['price'] ?? 0);
        $item_qty = intval($item['quantity'] ?? 0);
        $g_total_amount += ($item_price * $item_qty);
    }
    
    // Get service charge and discount if provided
    $service_charge = isset($input['service_charge']) ? floatval($input['service_charge']) : 0;
    $discount_amount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : 0;
    $net_total_amount = max(0, $g_total_amount + $service_charge - $discount_amount);
    
    // Start transaction
    mysqli_begin_transaction($connection);
    
    try {
        // 1. Create main order with total amounts
        $order_sql = "INSERT INTO orders (
            order_type, 
            order_status, 
            hall_id, 
            table_id, 
            comments, 
            terminal, 
            order_taker_id,
            branch_id,
            g_total_amount,
            service_charge,
            discount_amount,
            net_total_amount,
            created_at,
            updated_at
        ) VALUES (?, 'Running', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $order_stmt = mysqli_prepare($connection, $order_sql);
        mysqli_stmt_bind_param($order_stmt, "siiisiidddd", $order_type, $hall_id, $table_id, $comments, $terminal, $order_taker_id, $branch_id, $g_total_amount, $service_charge, $discount_amount, $net_total_amount);
        mysqli_stmt_execute($order_stmt);
        $order_id = mysqli_insert_id($connection);
        mysqli_stmt_close($order_stmt);
        
        // 2. Get dish details with category and kitchen_id from categories table
        $dish_ids = array_column($items, 'dish_id');
        $placeholders = str_repeat('?,', count($dish_ids) - 1) . '?';
        $dish_sql = "SELECT d.dish_id, d.name, d.price, d.category_id, 
                            c.kitchen_id, c.name as category_name
                     FROM dishes d
                     LEFT JOIN categories c ON d.category_id = c.category_id AND d.branch_id = c.branch_id AND d.terminal = c.terminal
                     LEFT JOIN kitchens k ON c.kitchen_id = k.kitchen_id AND c.branch_id = k.branch_id AND c.terminal = k.terminal
                     WHERE d.dish_id IN ($placeholders)";
        
        $dish_stmt = mysqli_prepare($connection, $dish_sql);
        mysqli_stmt_bind_param($dish_stmt, str_repeat('i', count($dish_ids)), ...$dish_ids);
        mysqli_stmt_execute($dish_stmt);
        $dish_result = mysqli_stmt_get_result($dish_stmt);
        
        $dishes_map = [];
        while ($dish = mysqli_fetch_assoc($dish_result)) {
            $dishes_map[$dish['dish_id']] = $dish;
        }
        mysqli_stmt_close($dish_stmt);
        
        // 3. Group items by kitchen
        $kitchen_items = [];
        $order_details = [];
        
        foreach ($items as $item) {
            $dish_id = intval($item['dish_id']);
            $quantity = intval($item['quantity']);
            $price = floatval($item['price']);
            
            if (!isset($dishes_map[$dish_id])) {
                throw new Exception("Dish ID $dish_id not found");
            }
            
            $dish = $dishes_map[$dish_id];
            $kitchen_id = $dish['kitchen_id'] ?? null;
            
            if (!$kitchen_id) {
                throw new Exception("Dish '{$dish['name']}' does not have a kitchen assigned. Please assign a kitchen to its category.");
            }
            
            // Create order detail - Check if order_items table exists
            $check_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
            $has_order_items_table = ($check_table && mysqli_num_rows($check_table) > 0);
            
            $total_amount = $price * $quantity;
            
            if ($has_order_items_table) {
                // Use order_items table (matches database schema)
                $detail_sql = "INSERT INTO order_items (
                    order_id, dish_id, quantity, price, total_amount, branch_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $detail_stmt = mysqli_prepare($connection, $detail_sql);
                mysqli_stmt_bind_param($detail_stmt, "iiiddi", $order_id, $dish_id, $quantity, $price, $total_amount, $branch_id);
                mysqli_stmt_execute($detail_stmt);
                $order_detail_id = mysqli_insert_id($connection);
                mysqli_stmt_close($detail_stmt);
            } else {
                // Fallback to orderdetails table (for backward compatibility)
                $detail_sql = "INSERT INTO orderdetails (
                    orderid, userid, p_id, rate, qnty, total, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $orderid_str = 'ORD-' . $order_id;
                $userid = intval($order_taker_id);
                $detail_stmt = mysqli_prepare($connection, $detail_sql);
                mysqli_stmt_bind_param($detail_stmt, "siididd", $orderid_str, $userid, $dish_id, $price, $quantity, $total_amount);
                mysqli_stmt_execute($detail_stmt);
                $order_detail_id = mysqli_insert_id($connection);
                mysqli_stmt_close($detail_stmt);
            }
            
            $order_details[] = [
                'order_detail_id' => $order_detail_id,
                'dish_id' => $dish_id,
                'dish_name' => $dish['name'],
                'quantity' => $quantity,
                'price' => $price,
                'total_amount' => $total_amount,
                'kitchen_id' => $kitchen_id
            ];
            
            // Group by kitchen
            if (!isset($kitchen_items[$kitchen_id])) {
                $kitchen_items[$kitchen_id] = [];
            }
            
            $kitchen_items[$kitchen_id][] = [
                'order_detail_id' => $order_detail_id,
                'dish_id' => $dish_id,
                'dish_name' => $dish['name'],
                'category_name' => $dish['category_name'],
                'quantity' => $quantity,
                'price' => $price,
                'total_amount' => $total_amount,
                'notes' => $comments
            ];
        }
        
        // 4. Create kitchen_order_status for each kitchen
        foreach ($kitchen_items as $kitchen_id => $items_list) {
            $status_sql = "INSERT INTO kitchen_order_status (
                order_id, kitchen_id, status, items_total, items_completed, created_at, updated_at
            ) VALUES (?, ?, 'Pending', ?, 0, NOW(), NOW())";
            
            $items_count = count($items_list);
            $status_stmt = mysqli_prepare($connection, $status_sql);
            mysqli_stmt_bind_param($status_stmt, "iii", $order_id, $kitchen_id, $items_count);
            mysqli_stmt_execute($status_stmt);
            mysqli_stmt_close($status_stmt);
        }
        
        // 5. Create order_items_kitchen entries
        foreach ($order_details as $detail) {
            $kitchen_item_sql = "INSERT INTO order_items_kitchen (
                order_id, order_detail_id, kitchen_id, dish_id, dish_name, quantity, price, status, notes, branch_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, NOW())";
            
            $kitchen_item_stmt = mysqli_prepare($connection, $kitchen_item_sql);
            // Type string: i=order_id, i=order_detail_id, i=kitchen_id, i=dish_id, s=dish_name, i=quantity, d=price, s=comments, i=branch_id (9 params)
            // Parameters: order_id(i), order_detail_id(i), kitchen_id(i), dish_id(i), dish_name(s), quantity(i), price(d), comments(s), branch_id(i)
            // Correct type string for 9 parameters: iiiisidssi (9 chars: 4i + s + i + d + s + i)
            $type_string = "i" . "i" . "i" . "i" . "s" . "i" . "d" . "s" . "i"; // 9 characters
            mysqli_stmt_bind_param($kitchen_item_stmt, $type_string, 
                $order_id, 
                $detail['order_detail_id'],
                $detail['kitchen_id'],
                $detail['dish_id'],
                $detail['dish_name'],
                $detail['quantity'],
                $detail['price'],
                $comments,
                $branch_id
            );
            mysqli_stmt_execute($kitchen_item_stmt);
            mysqli_stmt_close($kitchen_item_stmt);
        }
        
        // Commit transaction
        mysqli_commit($connection);
        
        // Automatically print kitchen receipts after order is created
        // Do this after commit so order is saved even if printing fails
        // Use direct function call to avoid HTTP/CORS issues
        $print_results = [];
        if (!empty($kitchen_items)) {
            // Include the print function
            require_once __DIR__ . '/print_kitchen_function.php';
            
            foreach ($kitchen_items as $kitchen_id => $items) {
                // Call print function directly (no HTTP needed, avoids CORS issues)
                $print_response = print_kitchen_receipt_direct($connection, $order_id, $kitchen_id, $branch_id);
                
                $print_results[$kitchen_id] = $print_response;
                
                if (isset($print_response['success']) && $print_response['success']) {
                    // Success - log for debugging
                    error_log("Kitchen Print Success: KOT sent to " . ($print_response['kitchen_name'] ?? 'kitchen') . " (IP: " . ($print_response['printer_ip'] ?? 'N/A') . ") for order $order_id");
                } else {
                    // Print failed - log but don't fail the order
                    $error_msg = $print_response['message'] ?? 'Unknown error';
                    error_log("Kitchen Print Error: $error_msg for kitchen $kitchen_id, order $order_id. Printer IP: " . ($print_response['printer_ip'] ?? 'N/A'));
                }
            }
        }
        
        // Get kitchen names
        $kitchen_ids = array_keys($kitchen_items);
        if (count($kitchen_ids) > 0) {
            $kitchen_placeholders = str_repeat('?,', count($kitchen_ids) - 1) . '?';
            $kitchen_name_sql = "SELECT kitchen_id, title FROM kitchens WHERE kitchen_id IN ($kitchen_placeholders) AND branch_id = ?";
            $kitchen_name_stmt = mysqli_prepare($connection, $kitchen_name_sql);
            $params = array_merge($kitchen_ids, [$branch_id]);
            mysqli_stmt_bind_param($kitchen_name_stmt, str_repeat('i', count($kitchen_ids)) . 'i', ...$params);
            mysqli_stmt_execute($kitchen_name_stmt);
            $kitchen_name_result = mysqli_stmt_get_result($kitchen_name_stmt);
            
            $kitchen_names = [];
            while ($kitchen = mysqli_fetch_assoc($kitchen_name_result)) {
                $kitchen_names[$kitchen['kitchen_id']] = $kitchen['title'];
            }
            mysqli_stmt_close($kitchen_name_stmt);
        } else {
            $kitchen_names = [];
        }
        
        // Prepare response
        $kitchen_orders = [];
        foreach ($kitchen_items as $kitchen_id => $items_list) {
            $kitchen_orders[] = [
                'kitchen_id' => $kitchen_id,
                'kitchen_name' => $kitchen_names[$kitchen_id] ?? "Kitchen $kitchen_id",
                'items' => $items_list,
                'items_count' => count($items_list)
            ];
        }
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "message" => "Order created and routed to kitchens successfully",
            "data" => [
                "order_id" => $order_id,
                "order_type" => $order_type,
                "kitchen_orders" => $kitchen_orders,
                "print_results" => $print_results
            ]
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Create Order Error: " . $e->getMessage());
    
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
