<?php
require_once '../api/cors_headers.php';
include("config.php");

// Set header response types
header('Content-Type: application/json; charset=utf-8');

// Force strict POST method checking
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. Only POST requests are permitted.']);
    exit;
}

// Get POST data
$order_id = $_POST["order_id"] ?? null;
$customer_id = $_POST["customer_id"] ?? null;
$order_type = $_POST["order_type"] ?? null;
$order_status = $_POST["order_status"] ?? null;
$service_charge = isset($_POST["service_charge"]) ? floatval($_POST["service_charge"]) : 0.00;
$g_total_amount = isset($_POST["g_total_amount"]) ? floatval($_POST["g_total_amount"]) : 0.00;
$discount_amount = isset($_POST["discount_amount"]) ? floatval($_POST["discount_amount"]) : 0.00;
$net_total_amount = isset($_POST["net_total_amount"]) ? floatval($_POST["net_total_amount"]) : 0.00;
$order_taker_id = $_POST["order_taker_id"] ?? null;
$bill_by = $_POST["bill_by"] ?? null;
$hall_id = $_POST["hall_id"] ?? null;
$table_id = $_POST["table_id"] ?? null;
$comments = $_POST["comments"] ?? null;
$terminal = $_POST["terminal"] ?? null;
$payment_mode = $_POST["payment_mode"] ?? null;
$c_name = $_POST["c_name"] ?? null;
$c_phoneno = $_POST["c_phoneno"] ?? null;
$c_address = $_POST["c_address"] ?? null;

$order_details_raw = $_POST["order_details"] ?? null;

// Decode order_details if it's a JSON string
$order_details = [];
if ($order_details_raw) {
    $order_details = json_decode($order_details_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON in order details: ' . json_last_error_msg(),
        ]);
        exit;
    }
}

// Current date for timestamps
$current_date = date("Y-m-d H:i:s");

try {
    // 1. FIX: Ensure database connection instance is verified early
    if (!isset($connection) || !$connection) {
        throw new Exception("Database engine connection is unavailable.");
    }

    // Begin transaction
    mysqli_begin_transaction($connection);

    // Calculate net_total_amount if not provided
    if ($net_total_amount <= 0) {
        $net_total_amount = max(0, $g_total_amount + $service_charge - $discount_amount);
    }
    
    // Typecast parameters cleanly
    $customer_id = $customer_id ? intval($customer_id) : null;
    $order_taker_id = $order_taker_id ? intval($order_taker_id) : null;
    $bill_by = $bill_by ? intval($bill_by) : null;
    $hall_id = $hall_id ? intval($hall_id) : null;
    $table_id = $table_id ? intval($table_id) : null;
    $terminal = $terminal ? intval($terminal) : 1;

    if (empty($order_id)) {
        $sql = "INSERT INTO orders (customer_id, order_type, order_status, service_charge, 
                g_total_amount, discount_amount, net_total_amount, order_taker_id, bill_by, hall_id, table_id, comments, 
                terminal, created_at, updated_at, c_name, c_phoneno, c_address, payment_mode) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) throw new Exception('Failed preparing order query framework.');
        
        mysqli_stmt_bind_param($stmt, "issddddiiisssssssss", 
            $customer_id, $order_type, $order_status, $service_charge,
            $g_total_amount, $discount_amount, $net_total_amount, $order_taker_id, $bill_by, $hall_id,
            $table_id, $comments, $terminal, $current_date, $current_date, $c_name, $c_phoneno, $c_address, $payment_mode);
        
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed processing structural order details.');
        }
        
        $order_id = mysqli_insert_id($connection);
        mysqli_stmt_close($stmt);
    } else {
        $sql = "UPDATE orders SET customer_id = ?, order_type = ?, order_status = ?, service_charge = ?, 
                g_total_amount = ?, discount_amount = ?, net_total_amount = ?, order_taker_id = ?, bill_by = ?, 
                hall_id = ?, table_id = ?, comments = ?, terminal = ?, c_name = ?, c_phoneno = ?, 
                payment_mode = ?, c_address = ?, updated_at = ? WHERE order_id = ?";
        
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) throw new Exception('Failed preparing record refresh schema.');
        
        mysqli_stmt_bind_param($stmt, "issddddiiissssssssi",
            $customer_id, $order_type, $order_status, $service_charge,
            $g_total_amount, $discount_amount, $net_total_amount, $order_taker_id, $bill_by, $hall_id,
            $table_id, $comments, $terminal, $c_name, $c_phoneno, $payment_mode, $c_address, $current_date, $order_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed updating targeting entity record details.');
        }
        mysqli_stmt_close($stmt);

        // Clear existing items out safely
        $deleteItemsSql = "DELETE FROM order_items WHERE order_id = ?";
        $delete_stmt = mysqli_prepare($connection, $deleteItemsSql);
        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "i", $order_id);
            if (!mysqli_stmt_execute($delete_stmt)) {
                mysqli_stmt_close($delete_stmt);
                throw new Exception('Failed processing record synchronization purge.');
            }
            mysqli_stmt_close($delete_stmt);
        }
    }

    // 2. FIX: Prepare the line item insertion once, then execute inside the loop
    if (!empty($order_details)) {
        $detailSql = "INSERT INTO order_items (order_id, dish_id, quantity, price, total_amount, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $detail_stmt = mysqli_prepare($connection, $detailSql);
        
        if (!$detail_stmt) throw new Exception("Failed constructing detail logging query parameters.");

        foreach ($order_details as $detail) {
            $dish_id = isset($detail["dish_id"]) ? intval($detail["dish_id"]) : 0;
            $quantity = isset($detail["quantity"]) ? intval($detail["quantity"]) : 0;
            $price = isset($detail["price"]) ? floatval($detail["price"]) : 0.00;
            $total_amount = isset($detail["total_amount"]) ? floatval($detail["total_amount"]) : 0.00;

            if (empty($dish_id) || empty($quantity) || $price < 0) {
                mysqli_stmt_close($detail_stmt);
                throw new Exception("Missing or invalid required properties found inside product lists.");
            }

            mysqli_stmt_bind_param($detail_stmt, "iiiddss", $order_id, $dish_id, $quantity, $price, $total_amount, $current_date, $current_date);
            
            if (!mysqli_stmt_execute($detail_stmt)) {
                mysqli_stmt_close($detail_stmt);
                throw new Exception("Failed recording granular target basket transactions.");
            }
        }
        mysqli_stmt_close($detail_stmt);
    }

    // Update physical layout tables
    if ($table_id) {
        $table_status = ($order_status == "Complete") ? 'Available' : 'Running';
        $updateTableSql = "UPDATE tables SET status = ? WHERE table_id = ?";
        $table_stmt = mysqli_prepare($connection, $updateTableSql);
        if ($table_stmt) {
            mysqli_stmt_bind_param($table_stmt, "si", $table_status, $table_id);
            if (!mysqli_stmt_execute($table_stmt)) {
                mysqli_stmt_close($table_stmt);
                throw new Exception("Failed updating floor layout details.");
            }
            mysqli_stmt_close($table_stmt);
        }
    }

    // Everything passed perfectly inside database transactional loops. Commit!
    mysqli_commit($connection);
    
    // 3. FIX: Batch-fetch kitchen mappings using a single array lookup query instead of loop calls
    $kot_printed = false;
    $kitchens_printed = [];
    
    if (!empty($order_id) && !empty($order_details)) {
        require_once __DIR__ . '/../api/print_kitchen_function.php';
        
        // Grab associated branch safely
        $order_branch_id = null;
        $get_branch_sql = "SELECT branch_id FROM orders WHERE order_id = ? LIMIT 1";
        $get_branch_stmt = mysqli_prepare($connection, $get_branch_sql);
        if ($get_branch_stmt) {
            mysqli_stmt_bind_param($get_branch_stmt, "i", $order_id);
            mysqli_stmt_execute($get_branch_stmt);
            $branch_result = mysqli_stmt_get_result($get_branch_stmt);
            if ($branch_data = mysqli_fetch_assoc($branch_result)) {
                $order_branch_id = !empty($branch_data['branch_id']) ? intval($branch_data['branch_id']) : null;
            }
            mysqli_stmt_close($get_branch_stmt);
        }

        // Gather all distinct dish IDs from our payload
        $dish_ids = array_filter(array_map(function($d) { return isset($d['dish_id']) ? intval($d['dish_id']) : 0; }, $order_details));
        
        if (!empty($dish_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($dish_ids), '?'));
            $kitchen_lookup_sql = "SELECT DISTINCT c.kid as kitchen_id 
                                   FROM dishes d
                                   JOIN categories c ON d.category_id = c.category_id 
                                   WHERE d.dish_id IN ($ids_placeholder) AND c.kid IS NOT NULL";
            
            $kitchen_stmt = mysqli_prepare($connection, $kitchen_lookup_sql);
            if ($kitchen_stmt) {
                $types = str_repeat('i', count($dish_ids));
                mysqli_stmt_bind_param($kitchen_stmt, $types, ...array_values($dish_ids));
                mysqli_stmt_execute($kitchen_stmt);
                $k_result = mysqli_stmt_get_result($kitchen_stmt);
                
                while ($k_row = mysqli_fetch_assoc($k_result)) {
                    $kitchen_id = intval($k_row['kitchen_id']);
                    $print_response = print_kitchen_receipt_direct($connection, $order_id, $kitchen_id, $order_branch_id);
                    if (isset($print_response['success']) && $print_response['success']) {
                        $kitchens_printed[] = $kitchen_id;
                        $kot_printed = true;
                    }
                }
                mysqli_stmt_close($kitchen_stmt);
            }
        }
    }

    // Build operational success package payload
    http_response_code(200);
    $response = [
        'status' => 'success',
        'message' => empty($_POST["order_id"]) ? 'Order created successfully.' : 'Order updated successfully.',
        'order_id' => $order_id,
    ];
    if ($kot_printed) {
        $response['kot_printed'] = true;
        $response['kitchens_printed'] = count($kitchens_printed);
    }
    echo json_encode($response);

} catch (Throwable $e) {
    // Safely verify if active connections can rollback execution parameters
    if (isset($connection) && $connection) {
        mysqli_rollback($connection);
    }
    error_log("Upload Order Processing Error Trace: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An internal database crash or tracking processing breakdown occurred.'
    ]);
} finally {
    if (isset($connection) && $connection) {
        mysqli_close($connection);
    }
}
?>