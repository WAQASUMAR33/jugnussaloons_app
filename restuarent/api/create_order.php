<?php
require_once 'cors_headers.php';
/**
 * Create Order API
 * Creates order with order items matching actual database structure
 * Supports both JSON and form data
 * Database: orders table with order_id, order_type, order_status, etc.
 * Database: order_items table with item_id, order_id, dish_id, quantity, price, total_amount
 */

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear all output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Send JSON error response
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => false,
            "message" => "Fatal error: " . $error['message']
        ]);
        
        exit();
    }
});

// Start output buffering to catch any accidental output
ob_start();

// Include config after headers
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
} catch (Error $e) {
    // Catch fatal errors from config.php
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    exit();
}

// Check request method - only allow POST
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
if ($request_method !== 'POST') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed. This endpoint only accepts POST requests.",
        "received_method" => $request_method
    ]);
    exit();
}

// Get input data - handle both JSON and form data
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// If JSON decode failed or returned null, try $_POST
if ($input === null || !is_array($input) || empty($input)) {
    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        // If both JSON and POST are empty, return error
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "No data received. Please send order data in JSON format or as form data.",
            "debug" => [
                "raw_input_length" => strlen($raw_input),
                "json_decode_error" => json_last_error_msg(),
                "post_data_count" => count($_POST)
            ]
        ]);
        exit();
    }
}

// Check connection
if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Get detailed error message
    $error_msg = "Database connection failed";
    $connect_error = mysqli_connect_error();
    if ($connect_error) {
        $error_msg = "Database connection failed: " . $connect_error;
        
        // Provide helpful message for common errors
        if (strpos($connect_error, "Access denied") !== false) {
            $error_msg = "Database access denied. Please check your database credentials in config.php. " . 
                        "If MySQL requires a password, update DB_PASS in config.php";
        } elseif (strpos($connect_error, "Unknown database") !== false) {
            $error_msg = "Database '" . DB_NAME . "' not found. Please check DB_NAME in config.php and ensure the database exists.";
        } elseif (strpos($connect_error, "Unknown MySQL server host") !== false) {
            $error_msg = "Cannot connect to MySQL server. Check DB_HOST in config.php (should be 'localhost' for WAMP)";
        }
    }
    
    echo json_encode(["success" => false, "message" => $error_msg]);
    exit();
}

// Verify connection is still alive
if (!mysqli_ping($connection)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Database connection lost. Please try again."]);
    exit();
}

// Get order data - matching actual database structure
$customer_id = isset($input['customer_id']) ? intval($input['customer_id']) : null;
$order_type = $input['order_type'] ?? ($_POST['order_type'] ?? 'Dine In'); // Dine In, Take Away, Delivery
$order_status = $input['order_status'] ?? ($_POST['order_status'] ?? 'Running');
$service_charge = isset($input['service_charge']) ? floatval($input['service_charge']) : 0.00;
$order_taker_id = isset($input['order_taker_id']) ? intval($input['order_taker_id']) : (isset($_POST['order_taker_id']) ? intval($_POST['order_taker_id']) : 1);
$payment_mode = $input['payment_mode'] ?? ($_POST['payment_mode'] ?? 'Cash');
$bill_by = isset($input['bill_by']) ? intval($input['bill_by']) : 0;
$hall_id = isset($input['hall_id']) ? intval($input['hall_id']) : 0;
$table_id = isset($input['table_id']) && $input['table_id'] ? intval($input['table_id']) : null;
$comments = $input['comments'] ?? ($_POST['comments'] ?? '');
$customer_name = isset($input['customer_name']) ? trim($input['customer_name']) : '';
$terminal = isset($input['terminal']) ? intval($input['terminal']) : (isset($_POST['terminal']) ? intval($_POST['terminal']) : 1);
$branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : null;
$discount_amount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : (isset($_POST['discount']) ? floatval($_POST['discount']) : 0);
$order_items = $input['items'] ?? ($_POST['items'] ?? []); // Array of items

// Validate branch_id
if (!$branch_id_input || $branch_id_input === '' || $branch_id_input === 'null' || $branch_id_input === 'undefined') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'branch_id is required',
        'message' => 'Branch ID must be provided'
    ]);
    exit();
}

$branch_id = intval($branch_id_input);
if ($branch_id <= 0) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid branch_id',
        'message' => 'Branch ID must be a valid positive integer'
    ]);
    exit();
}

// Handle JSON string for items if passed as string
if (is_string($order_items)) {
    $order_items = json_decode($order_items, true) ?? [];
}

// Validate required fields
if (empty($order_items) || !is_array($order_items) || count($order_items) === 0) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'items are required',
        'message' => 'Order must contain at least one item'
    ]);
    exit();
}

// Validate order_type
$valid_order_types = ['Dine In', 'Take Away', 'Delivery'];
if (!in_array($order_type, $valid_order_types)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid order_type',
        'message' => 'Order type must be one of: ' . implode(', ', $valid_order_types)
    ]);
    exit();
}

// For Dine In orders, table_id is required
if ($order_type === 'Dine In' && !$table_id) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'table_id is required for Dine In orders',
        'message' => 'Table ID must be provided for Dine In orders'
    ]);
    exit();
}

// Handle customer_name
$customer_name = isset($input['customer_name']) ? trim($input['customer_name']) : '';

// Calculate totals
$g_total_amount = 0;
foreach ($order_items as $item) {
    $item_price = floatval($item['price'] ?? $item['rate'] ?? 0);
    $item_qty = intval($item['quantity'] ?? $item['qnty'] ?? 0);
    $g_total_amount += ($item_price * $item_qty);
}

$net_total_amount = max(0, $g_total_amount + $service_charge - $discount_amount); // Ensure non-negative

// Generate order number
$order_number = 'ORD-' . time() . '-' . rand(1000, 9999);
$orderid = $order_number; // Alias for compatibility

$current_date = date("Y-m-d H:i:s");

// Verify connection is still valid before starting transaction
if (!isset($connection) || !$connection || mysqli_ping($connection) === false) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Database connection lost"]);
    exit();
}

// Start transaction
mysqli_begin_transaction($connection);

try {
    // Insert order - matching actual database structure with branch_id
    // Note: orders table doesn't have c_name column - customer info stored via customer_id only
    $sql_order = "INSERT INTO orders (
                    order_type, 
                    order_status,
                    hall_id, 
                    table_id, 
                    comments, 
                    terminal, 
                    order_taker_id,
                    branch_id,
                    customer_id,
                    g_total_amount,
                    service_charge,
                    discount_amount,
                    net_total_amount,
                    payment_mode,
                    created_at,
                    updated_at
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt_order = mysqli_prepare($connection, $sql_order);
    
    if (!$stmt_order) {
        throw new Exception("Error preparing order statement: " . mysqli_error($connection));
    }
    
    // Handle nullable customer_id - use 0 if null for INT binding
    $customer_id_bind = ($customer_id === null || $customer_id === '') ? 0 : intval($customer_id);
    
    // Handle nullable table_id - use 0 if null for INT binding
    $table_id_bind = ($table_id === null || $table_id === '') ? 0 : intval($table_id);
    
    // Bind parameters: 14 parameters total (removed status and c_name columns)
    // 1. order_type (s), 2. order_status (s),
    // 3. hall_id (i), 4. table_id (i), 5. comments (s), 
    // 6. terminal (i), 7. order_taker_id (i), 8. branch_id (i), 9. customer_id (i),
    // 10. g_total_amount (d), 11. service_charge (d), 12. discount_amount (d), 13. net_total_amount (d),
    // 14. payment_mode (s)
    // Type string: "ssiisiiiidddds" (14 characters)
    
    mysqli_stmt_bind_param($stmt_order, "ssiisiiiidddds", 
        $order_type,           // 1. s - order_type
        $order_status,         // 2. s - order_status
        $hall_id,              // 3. i - hall_id
        $table_id_bind,        // 4. i - table_id
        $comments,             // 5. s - comments
        $terminal,             // 6. i - terminal
        $order_taker_id,       // 7. i - order_taker_id
        $branch_id,            // 8. i - branch_id
        $customer_id_bind,     // 9. i - customer_id
        $g_total_amount,       // 10. d - g_total_amount
        $service_charge,       // 11. d - service_charge
        $discount_amount,      // 12. d - discount_amount
        $net_total_amount,     // 13. d - net_total_amount
        $payment_mode          // 14. s - payment_mode
    );
    
    if (!mysqli_stmt_execute($stmt_order)) {
        throw new Exception("Error creating order: " . mysqli_error($connection));
    }
    
    $order_id = mysqli_insert_id($connection);
    mysqli_stmt_close($stmt_order);
    
    // Validate that order_id was generated successfully
    if (!$order_id || $order_id <= 0) {
        throw new Exception("Failed to get order ID after insertion. Order may not have been created.");
    }
    
    // Get kitchen_id for each dish from categories
    $dish_ids = [];
    foreach ($order_items as $item) {
        $dish_id = $item['dish_id'] ?? $item['product_id'] ?? $item['p_id'] ?? '';
        if (!empty($dish_id)) {
            $dish_ids[] = intval($dish_id);
        }
    }
    
    // Get dish details with kitchen_id from categories
    $dishes_kitchen_map = [];
    if (!empty($dish_ids)) {
        $placeholders = str_repeat('?,', count($dish_ids) - 1) . '?';
        $dish_kitchen_sql = "SELECT d.dish_id, d.name, d.price, d.category_id,
                            c.kitchen_id, c.name as category_name
                            FROM dishes d
                            LEFT JOIN categories c ON d.category_id = c.category_id 
                                AND d.branch_id = c.branch_id 
                                AND d.terminal = c.terminal
                            WHERE d.dish_id IN ($placeholders) 
                                AND d.branch_id = ? AND d.terminal = ?";
        
        $dish_kitchen_stmt = mysqli_prepare($connection, $dish_kitchen_sql);
        if ($dish_kitchen_stmt) {
            $params = array_merge($dish_ids, [$branch_id, $terminal]);
            $types = str_repeat('i', count($dish_ids)) . 'ii';
            mysqli_stmt_bind_param($dish_kitchen_stmt, $types, ...$params);
            mysqli_stmt_execute($dish_kitchen_stmt);
            $dish_kitchen_result = mysqli_stmt_get_result($dish_kitchen_stmt);
            
            while ($dish_row = mysqli_fetch_assoc($dish_kitchen_result)) {
                $dishes_kitchen_map[$dish_row['dish_id']] = [
                    'kitchen_id' => $dish_row['kitchen_id'] ?? null,
                    'dish_name' => $dish_row['name'] ?? '',
                    'category_name' => $dish_row['category_name'] ?? ''
                ];
            }
            mysqli_stmt_close($dish_kitchen_stmt);
        }
    }
    
    // Track kitchens that need printing
    $kitchens_to_print = [];
    
    // Insert order items - matching order_items table structure
    foreach ($order_items as $item) {
        $dish_id = $item['dish_id'] ?? $item['product_id'] ?? $item['p_id'] ?? '';
        if (empty($dish_id)) {
            continue; // Skip invalid items
        }
        
        $dish_id = intval($dish_id);
        $item_price = floatval($item['price'] ?? $item['rate'] ?? 0);
        $item_qty = intval($item['quantity'] ?? $item['qnty'] ?? 1);
        $item_total = $item_price * $item_qty;
        
        // Get kitchen_id for this dish
        $kitchen_id = $dishes_kitchen_map[$dish_id]['kitchen_id'] ?? null;
        $dish_name = $dishes_kitchen_map[$dish_id]['dish_name'] ?? '';
        
        // Check if order_items table exists (only check once, cache the result)
        static $has_order_items_table = null;
        if ($has_order_items_table === null) {
            $check_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
            $has_order_items_table = ($check_table && mysqli_num_rows($check_table) > 0);
        }
        
        if ($has_order_items_table) {
            // Use order_items table - include branch_id
            $sql_item = "INSERT INTO order_items (order_id, dish_id, quantity, price, total_amount, branch_id) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_item = mysqli_prepare($connection, $sql_item);
            if (!$stmt_item) {
                throw new Exception("Error preparing order item statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($stmt_item, "iidddi", 
                $order_id, $dish_id, $item_qty, $item_price, $item_total, $branch_id);
        } else {
            // Fallback to orderdetails table
            // Columns: orderid (s), userid (i), p_id (i), rate (d), qnty (i), total (d), created_at (s), updated_at (s)
            // Type string: "siididss" (8 characters for 8 parameters)
            // 1. orderid (s), 2. userid (i), 3. p_id (i), 4. rate (d), 5. qnty (i), 6. total (d), 7. created_at (s), 8. updated_at (s)
            $sql_item = "INSERT INTO orderdetails (orderid, userid, p_id, rate, qnty, total, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_item = mysqli_prepare($connection, $sql_item);
            $orderid_str = 'ORD-' . $order_id;
            $userid = intval($order_taker_id);
            mysqli_stmt_bind_param($stmt_item, "siididss", 
                $orderid_str,    // 1. s - orderid (string)
                $userid,         // 2. i - userid (integer)
                $dish_id,        // 3. i - p_id (integer)
                $item_price,     // 4. d - rate (double)
                $item_qty,       // 5. i - qnty (integer)
                $item_total,     // 6. d - total (double)
                $current_date,   // 7. s - created_at (string)
                $current_date    // 8. s - updated_at (string)
            );
        }
        
        if (!$stmt_item) {
            throw new Exception("Error preparing order item statement: " . mysqli_error($connection));
        }
        
        if (!mysqli_stmt_execute($stmt_item)) {
            throw new Exception("Error creating order item: " . mysqli_error($connection));
        }
        
        // Get order_detail_id only if using order_items table (has auto-increment)
        $order_detail_id = null;
        if ($has_order_items_table) {
            $order_detail_id = mysqli_insert_id($connection);
        }
        
        mysqli_stmt_close($stmt_item);
        
        // Create order_items_kitchen entry if kitchen_id exists
        if ($kitchen_id && $kitchen_id > 0) {
            // Check if order_items_kitchen table exists
            static $has_order_items_kitchen = null;
            if ($has_order_items_kitchen === null) {
                $check_kitchen_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items_kitchen'");
                $has_order_items_kitchen = ($check_kitchen_table && mysqli_num_rows($check_kitchen_table) > 0);
            }
            
            if ($has_order_items_kitchen) {
                // If order_detail_id is null, use 0 or order_id as fallback
                $order_detail_id_bind = $order_detail_id ?? $order_id;
                
                $kitchen_item_sql = "INSERT INTO order_items_kitchen (
                    order_id, order_detail_id, kitchen_id, dish_id, dish_name, quantity, price, status, notes, branch_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, NOW())";
                
                $kitchen_item_stmt = mysqli_prepare($connection, $kitchen_item_sql);
                if ($kitchen_item_stmt) {
                    $item_notes = $item['notes'] ?? $item['comment'] ?? $comments ?? '';
                    mysqli_stmt_bind_param($kitchen_item_stmt, "iiiisidssi", 
                        $order_id,
                        $order_detail_id_bind,
                        $kitchen_id,
                        $dish_id,
                        $dish_name,
                        $item_qty,
                        $item_price,
                        $item_notes,
                        $branch_id
                    );
                    
                    if (mysqli_stmt_execute($kitchen_item_stmt)) {
                        // Track this kitchen for printing
                        if (!isset($kitchens_to_print[$kitchen_id])) {
                            $kitchens_to_print[$kitchen_id] = [];
                        }
                        $kitchens_to_print[$kitchen_id][] = [
                            'dish_name' => $dish_name,
                            'quantity' => $item_qty,
                            'price' => $item_price,
                            'notes' => $item_notes
                        ];
                    }
                    mysqli_stmt_close($kitchen_item_stmt);
                }
            } else {
                // Even if order_items_kitchen table doesn't exist, track for printing
                if (!isset($kitchens_to_print[$kitchen_id])) {
                    $kitchens_to_print[$kitchen_id] = [];
                }
                $kitchens_to_print[$kitchen_id][] = [
                    'dish_name' => $dish_name,
                    'quantity' => $item_qty,
                    'price' => $item_price,
                    'notes' => $item['notes'] ?? $item['comment'] ?? $comments ?? ''
                ];
            }
        }
    }
    
    // Update table status if Dine In order
    if ($order_type === 'Dine In' && $table_id_bind > 0) {
        $update_table_sql = "UPDATE tables 
                            SET status = 'Occupied' 
                            WHERE table_id = ? AND branch_id = ? AND terminal = ?";
        $update_table_stmt = mysqli_prepare($connection, $update_table_sql);
        if ($update_table_stmt) {
            mysqli_stmt_bind_param($update_table_stmt, "iii", $table_id_bind, $branch_id, $terminal);
            mysqli_stmt_execute($update_table_stmt);
            mysqli_stmt_close($update_table_stmt);
        }
    }
    
    // Commit transaction
    if (!mysqli_commit($connection)) {
        throw new Exception("Failed to commit transaction: " . mysqli_error($connection));
    }
    
    // Automatically print kitchen receipts after order is created
    // Do this after commit so order is saved even if printing fails
    // Use direct function call to avoid HTTP/CORS issues
    if (!empty($kitchens_to_print)) {
        // Include the print function
        require_once __DIR__ . '/print_kitchen_function.php';
        
        foreach ($kitchens_to_print as $kitchen_id => $items) {
            // Call print function directly (no HTTP needed, avoids CORS issues)
            $print_response = print_kitchen_receipt_direct($connection, $order_id, $kitchen_id, $branch_id);
            
            if (isset($print_response['success']) && $print_response['success']) {
                // Success - log for debugging
                error_log("Kitchen Print Success: KOT sent to " . ($print_response['kitchen_name'] ?? 'kitchen') . " (IP: " . ($print_response['printer_ip'] ?? 'N/A') . ") for order $order_id");
            } else {
                // Print failed
                $error_msg = $print_response['message'] ?? 'Unknown error';
                error_log("Kitchen Print Error: $error_msg for kitchen $kitchen_id, order $order_id. Printer IP: " . ($print_response['printer_ip'] ?? 'N/A'));
            }
        }
    }
    
    // Get created order details with branch info
    $sql_get_order = "SELECT o.*, 
                      t.table_number,
                      b.branch_name,
                      bill.payment_status,
                      bill.bill_id
                      FROM orders o
                      LEFT JOIN tables t ON o.table_id = t.table_id AND o.branch_id = t.branch_id AND o.terminal = t.terminal
                      LEFT JOIN branches b ON o.branch_id = b.branch_id
                      LEFT JOIN bills bill ON o.order_id = bill.order_id
                      WHERE o.order_id = ?";
    $stmt_get = mysqli_prepare($connection, $sql_get_order);
    if (!$stmt_get) {
        throw new Exception("Error preparing fetch statement: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($stmt_get, "i", $order_id);
    
    if (!mysqli_stmt_execute($stmt_get)) {
        throw new Exception("Error executing fetch statement: " . mysqli_error($connection));
    }
    
    $result = mysqli_stmt_get_result($stmt_get);
    if (!$result) {
        throw new Exception("Error getting fetch result: " . mysqli_error($connection));
    }
    
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_get);
    
    // If order not found after commit, create a minimal response with the order_id we know was created
    if (!$order || empty($order['order_id'])) {
        // Order was created but fetch failed - create minimal response
        $order = [
            'order_id' => $order_id,
            'order_type' => $order_type,
            'order_status' => $order_status,
            'table_id' => $table_id_bind > 0 ? $table_id_bind : null,
            'hall_id' => $hall_id > 0 ? $hall_id : null,
            'branch_id' => $branch_id,
            'terminal' => $terminal,
            'g_total_amount' => $g_total_amount,
            'net_total_amount' => $net_total_amount,
            'service_charge' => $service_charge,
            'discount_amount' => $discount_amount,
            'payment_mode' => $payment_mode,
            'created_at' => $current_date
        ];
    }
    
    // Normalize branch_name
    $branch_name = $order['branch_name'] ?? null;
    if (!$branch_name && $order['branch_id']) {
        $branch_name = 'Branch ' . $order['branch_id'];
    }
    
    // Get order items
    $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
    $has_order_items_table = false;
    if ($check_table_items && mysqli_num_rows($check_table_items) > 0) {
        $has_order_items_table = true;
    }
    
    if ($has_order_items_table) {
        // Use order_items table - only join with dishes table (products table doesn't exist)
        $sql_get_items = "SELECT order_items.*, 
                          COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                          COALESCE(dishes.description, '') as description
                          FROM order_items 
                          LEFT JOIN dishes ON dishes.dish_id = order_items.dish_id 
                          WHERE order_items.order_id = ?";
        $stmt_items = mysqli_prepare($connection, $sql_get_items);
        if ($stmt_items) {
            mysqli_stmt_bind_param($stmt_items, "i", $order_id);
        }
    } else {
        // Fallback to orderdetails table - only join with dishes table
        $orderid_str = 'ORD-' . $order_id;
        $sql_get_items = "SELECT orderdetails.*, 
                          COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                          COALESCE(dishes.description, '') as description
                          FROM orderdetails 
                          LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                          WHERE orderdetails.orderid = ?";
        $stmt_items = mysqli_prepare($connection, $sql_get_items);
        if ($stmt_items) {
            mysqli_stmt_bind_param($stmt_items, "s", $orderid_str);
        }
    }
    
    if (!$stmt_items) {
        throw new Exception("Error preparing items query: " . mysqli_error($connection));
    }
    mysqli_stmt_execute($stmt_items);
    $result_items = mysqli_stmt_get_result($stmt_items);
    $items = [];
    while($row = mysqli_fetch_assoc($result_items)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt_items);
    
    // Prepare response data with normalized format
    $order_number_response = $order['order_number'] ?? ($order['order_id'] ? 'ORD-' . $order['order_id'] : null) ?? $order['orderid'] ?? '';
    
    // Get payment_status from order data (from bills table join)
    $payment_status = $order['payment_status'] ?? 'Unpaid';
    
    $response_data = [
        "success" => true,
        "message" => "Order created successfully",
        "data" => [
            "order_id" => intval($order['order_id']),
            "id" => intval($order['order_id']),
            "order_number" => $order_number_response,
            "orderid" => $order_number_response,
            "order_type" => $order['order_type'] ?? 'Dine In',
            "order_status" => $order['order_status'] ?? $order['status'] ?? 'Running',
            "status" => strtolower($order['order_status'] ?? $order['status'] ?? 'running'),
            "table_id" => $order['table_id'] ? intval($order['table_id']) : null,
            "table_number" => $order['table_number'] ?? null,
            "hall_id" => $order['hall_id'] ?? null,
            "hall_name" => $order['hall_name'] ?? null,
            "customer_id" => $order['customer_id'] ? intval($order['customer_id']) : null,
            "customer_name" => null, // Customer name would come from customers table via customer_id if needed
            "g_total_amount" => floatval($order['g_total_amount'] ?? $order['total_amount'] ?? $order['total'] ?? 0),
            "total" => floatval($order['total'] ?? $order['g_total_amount'] ?? $order['total_amount'] ?? 0),
            "subtotal" => floatval($order['g_total_amount'] ?? $order['total_amount'] ?? $order['total'] ?? 0),
            "net_total_amount" => floatval($order['net_total_amount'] ?? $order['netTotal'] ?? $order['net_total'] ?? 0),
            "netTotal" => floatval($order['net_total_amount'] ?? $order['netTotal'] ?? $order['net_total'] ?? 0),
            "discount_amount" => floatval($order['discount_amount'] ?? 0),
            "discount" => floatval($order['discount_amount'] ?? 0),
            "service_charge" => floatval($order['service_charge'] ?? 0),
            "payment_mode" => $order['payment_mode'] ?? 'Cash',
            "payment_status" => $payment_status,
            "is_paid" => ($payment_status === 'Paid'),
            "bill_id" => $order['bill_id'] ? intval($order['bill_id']) : null,
            "order_taker_id" => $order['order_taker_id'] ? intval($order['order_taker_id']) : null,
            "created_at" => $order['created_at'] ?? $current_date,
            "date" => $order['created_at'] ?? $current_date,
            "terminal" => intval($order['terminal'] ?? $terminal),
            "branch_id" => intval($order['branch_id'] ?? $branch_id),
            "branch_name" => $branch_name,
            "comments" => $order['comments'] ?? $comments ?? null,
            "items" => $items
        ]
    ];
    
    // Output JSON and ensure it's sent
    $json_output = json_encode($response_data);
    if ($json_output === false) {
        throw new Exception("JSON encoding failed: " . json_last_error_msg());
    }
    
    // Clear any output buffer content before sending JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Ensure JSON header is set
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Output the JSON
    echo $json_output;
    
    // Ensure output is sent immediately
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    
    // Force output flush
    flush();
    
} catch (Exception $e) {
    // Rollback transaction
    if (isset($connection) && $connection) {
        @mysqli_rollback($connection);
    }
    
    // Log error for debugging but don't expose sensitive details
    error_log("Create Order Error: " . $e->getMessage());
    
    // Clear any output buffer content
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Return clean JSON error response
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    $error_output = json_encode([
        "success" => false,
        "message" => "Failed to create order: " . $e->getMessage()
    ]);
    
    if ($error_output === false) {
        $error_output = json_encode([
            "success" => false,
            "message" => "Failed to create order: Unknown error occurred"
        ]);
    }
    
    echo $error_output;
    
    // Ensure output is sent immediately
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    
    // Force output flush
    flush();
    
    exit();
} catch (Error $e) {
    // Catch fatal errors (PHP 7+)
    // Rollback transaction
    if (isset($connection) && $connection) {
        @mysqli_rollback($connection);
    }
    
    // Log error
    error_log("Create Order Fatal Error: " . $e->getMessage());
    
    // Clear any output buffer content
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Return clean JSON error response
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => false,
        "message" => "Fatal error: " . $e->getMessage()
    ]);
    
    // Ensure output is sent immediately
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    
    flush();
    
    exit();
}

// Don't close connection here - let PHP handle it automatically
// Closing connection before output is fully sent can cause empty responses

exit();
?>
