<?php

/**
 * Bills Management API
 * Create, read, update bills
 * Database: bills table
 * 
 * FIXED VERSION - Prevents duplicate bills when updating payment status
 */
require_once 'cors_headers.php';
// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Register shutdown function to catch fatal errors
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

// Start output buffering
ob_start();

// Include config
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

/**
 * Helper function to update customer balance when credit bills are created/updated/deleted
 * 
 * @param mysqli $connection Database connection
 * @param int $customer_id Customer ID
 * @param float $amount Amount to add (positive) or subtract (negative)
 * @param string $operation Operation type: 'add', 'subtract', or 'set'
 * @return bool Success status
 */
function updateCustomerBalance($connection, $customer_id, $amount, $operation = 'add') {
    if (!$connection || !$customer_id || $customer_id <= 0) {
        return false;
    }
    
    $amount = floatval($amount);
    if ($amount <= 0) {
        return false; // Only process positive amounts
    }
    
    try {
        if ($operation === 'add') {
            // Add amount to customer balance
            $sql = "UPDATE customers 
                    SET balance = COALESCE(balance, 0) + ?,
                        updated_at = NOW()
                    WHERE customer_id = ?";
        } elseif ($operation === 'subtract') {
            // Subtract amount from customer balance (prevent negative)
            $sql = "UPDATE customers 
                    SET balance = GREATEST(COALESCE(balance, 0) - ?, 0),
                        updated_at = NOW()
                    WHERE customer_id = ?";
        } else {
            // Set balance to specific amount
            $sql = "UPDATE customers 
                    SET balance = ?,
                        updated_at = NOW()
                    WHERE customer_id = ?";
        }
        
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            error_log("Error preparing customer balance update: " . mysqli_error($connection));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "di", $amount, $customer_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error updating customer balance: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
        
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affected > 0) {
            error_log("Customer balance updated: customer_id=$customer_id, operation=$operation, amount=$amount");
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Exception updating customer balance: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to check if a bill is a credit bill
 * 
 * @param string $payment_method Payment method
 * @param string $payment_status Payment status
 * @param int $customer_id Customer ID (optional)
 * @return bool True if credit bill
 */
function isCreditBill($payment_method, $payment_status, $customer_id = null) {
    $payment_method_lower = strtolower(trim($payment_method ?? ''));
    $payment_status_lower = strtolower(trim($payment_status ?? ''));
    
    // Check if payment method is Credit
    if (in_array($payment_method_lower, ['credit', 'cred'])) {
        return true;
    }
    
    // Check if payment status is Credit
    if ($payment_status_lower === 'credit') {
        return true;
    }
    
    // Check if Unpaid with customer (credit sale)
    if ($payment_status_lower === 'unpaid' && $customer_id !== null && $customer_id > 0) {
        return true;
    }
    
    return false;
}

// Get input data
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (!$input || !is_array($input)) {
    $input = $_POST;
}

$method = $_SERVER['REQUEST_METHOD'];

// Extract key parameters
$payment_status = isset($input['payment_status']) ? trim($input['payment_status']) : null;
$bill_id = isset($input['bill_id']) ? intval($input['bill_id']) : 0;
$order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;

// Check connection
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
    
    // ============================================
    // CRITICAL FIX: UPDATE PAYMENT STATUS BY ORDER_ID
    // This must come BEFORE CREATE BILL to prevent duplicates
    // Only trigger if payment_status is provided WITHOUT total_amount (payment update, not bill creation)
    // Allow bill_id to be provided, but prioritize finding by order_id if bill_id is 0
    // ============================================
    if ($method === 'POST' && $payment_status && $order_id > 0 && !isset($input['total_amount'])) {
        // Payment status update requested with order_id
        // This is a PAYMENT UPDATE, not bill creation (bill creation includes total_amount)
        // Find existing bill and update it (DON'T CREATE NEW)
        
        $existing_bill = null;
        $actual_bill_id = 0;
        
        // If bill_id is provided, use it directly
        if ($bill_id > 0) {
            $find_sql = "SELECT b.*, o.customer_id 
                        FROM bills b 
                        LEFT JOIN orders o ON b.order_id = o.order_id 
                        WHERE b.bill_id = ?";
            $find_stmt = mysqli_prepare($connection, $find_sql);
            
            if (!$find_stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($find_stmt, "i", $bill_id);
            mysqli_stmt_execute($find_stmt);
            $result = mysqli_stmt_get_result($find_stmt);
            $existing_bill = mysqli_fetch_assoc($result);
            mysqli_stmt_close($find_stmt);
            
            if ($existing_bill) {
                $actual_bill_id = $existing_bill['bill_id'];
            }
        }
        
        // If bill not found by bill_id, try finding by order_id
        if (!$existing_bill) {
            $find_sql = "SELECT b.*, o.customer_id 
                        FROM bills b 
                        LEFT JOIN orders o ON b.order_id = o.order_id 
                        WHERE b.order_id = ? 
                        ORDER BY b.bill_id DESC 
                        LIMIT 1";
            $find_stmt = mysqli_prepare($connection, $find_sql);
            
            if (!$find_stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($find_stmt, "i", $order_id);
            mysqli_stmt_execute($find_stmt);
            $result = mysqli_stmt_get_result($find_stmt);
            $existing_bill = mysqli_fetch_assoc($result);
            mysqli_stmt_close($find_stmt);
            
            if ($existing_bill) {
                $actual_bill_id = $existing_bill['bill_id'];
            }
        }
        
        if (!$existing_bill) {
            // Bill not found - return error instead of creating new one
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Bill not found for this order. Please generate the bill first."
            ]);
            exit();
        }
        
        // Normalize payment_method if provided
        $normalized_payment_method = null;
        if (isset($input['payment_method']) && trim($input['payment_method']) !== '') {
            $payment_method_raw = trim($input['payment_method']);
            $payment_method_lower = strtolower($payment_method_raw);
            
            // Normalize payment method
            if (in_array($payment_method_lower, ['credit', 'cred'])) {
                $normalized_payment_method = 'Credit';
            } elseif (in_array($payment_method_lower, ['card', 'debit', 'credit card'])) {
                $normalized_payment_method = 'Card';
            } elseif (in_array($payment_method_lower, ['online', 'upi', 'digital', 'netbanking'])) {
                $normalized_payment_method = 'Online';
            } elseif (in_array($payment_method_lower, ['cash'])) {
                $normalized_payment_method = 'Cash';
            } else {
                $normalized_payment_method = ucfirst($payment_method_lower) ?: 'Cash';
            }
        } elseif (isset($existing_bill['payment_method']) && trim($existing_bill['payment_method']) !== '') {
            $normalized_payment_method = trim($existing_bill['payment_method']);
        } else {
            $normalized_payment_method = 'Cash'; // Default if nothing exists
        }
        
        // Normalize payment_status - handle empty strings and preserve existing if needed
        $normalized_payment_status = null;
        if (isset($input['payment_status']) && trim($input['payment_status']) !== '') {
            $normalized_payment_status = trim($input['payment_status']);
        } elseif (isset($existing_bill['payment_status']) && trim($existing_bill['payment_status']) !== '') {
            $normalized_payment_status = trim($existing_bill['payment_status']);
        } else {
            // If Credit payment, default to Unpaid, otherwise use existing or Unpaid
            if (strtolower($normalized_payment_method) === 'credit') {
                $normalized_payment_status = 'Unpaid';
            } else {
                $normalized_payment_status = 'Unpaid'; // Default
            }
        }
        
        // Validate payment_status enum
        if (!in_array($normalized_payment_status, ['Paid', 'Unpaid'])) {
            $normalized_payment_status = 'Unpaid';
        }
        
        // Build update query
        // Start with payment_status (required) and updated_at (MySQL function, no placeholder)
        $update_fields = ['payment_status = ?', 'updated_at = NOW()'];
        $update_values = [$normalized_payment_status];
        $types = 's'; // One type for payment_status
        
        // Always update payment_method
        $update_fields[] = 'payment_method = ?';
        $update_values[] = $normalized_payment_method;
        $types .= 's';
        
        // Note: cash_received and change are not stored in bills table
        // They are calculated/displayed in the frontend only
        
        // Add bill_id for WHERE clause (always needed)
        $update_values[] = $actual_bill_id;
        $types .= 'i';
        
        // Build SQL query
        $update_sql = "UPDATE bills SET " . implode(', ', $update_fields) . " WHERE bill_id = ?";
        
        // Verify counts match (for debugging)
        $placeholder_count = substr_count($update_sql, '?');
        $value_count = count($update_values);
        $type_count = strlen($types);
        
        if ($placeholder_count !== $value_count || $value_count !== $type_count) {
            error_log("Parameter mismatch - Placeholders: $placeholder_count, Values: $value_count, Types: $type_count");
            error_log("SQL: $update_sql");
            error_log("Types: $types");
            error_log("Values: " . print_r($update_values, true));
            throw new Exception("Parameter count mismatch in update query. Placeholders: $placeholder_count, Values: $value_count, Types: $type_count");
        }
        
        $update_stmt = mysqli_prepare($connection, $update_sql);
        
        if (!$update_stmt) {
            throw new Exception("Error preparing update statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($update_stmt, $types, ...$update_values);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            $error_msg = mysqli_error($connection);
            mysqli_stmt_close($update_stmt);
            throw new Exception("Error updating bill: " . $error_msg);
        }
        
        mysqli_stmt_close($update_stmt);
        
        // Handle customer balance update for payment status change
        if ($existing_bill) {
            $old_payment_method = $existing_bill['payment_method'] ?? 'Cash';
            $old_payment_status = $existing_bill['payment_status'] ?? 'Unpaid';
            $old_grand_total = floatval($existing_bill['grand_total'] ?? 0);
            $old_customer_id = isset($existing_bill['customer_id']) && $existing_bill['customer_id'] > 0 ? intval($existing_bill['customer_id']) : null;
            
            $old_was_credit = isCreditBill($old_payment_method, $old_payment_status, $old_customer_id);
            $new_is_credit = isCreditBill($normalized_payment_method, $normalized_payment_status, $old_customer_id);
            
            if ($old_was_credit && !$new_is_credit && $old_customer_id) {
                // Credit bill changed to non-credit (e.g., paid) - subtract old amount
                updateCustomerBalance($connection, $old_customer_id, $old_grand_total, 'subtract');
            } elseif (!$old_was_credit && $new_is_credit && $old_customer_id) {
                // Non-credit bill changed to credit - add new amount
                updateCustomerBalance($connection, $old_customer_id, $old_grand_total, 'add');
            }
        }
        
        // Update order status based on payment status and method
        if ($existing_bill && isset($existing_bill['order_id'])) {
            $order_id_to_update = $existing_bill['order_id'];
            
            // If payment_status is "Paid", update order status to "Complete"
            if ($normalized_payment_status === 'Paid') {
                $update_order_sql = "UPDATE orders SET 
                                    order_status = 'Complete',
                                    payment_mode = ?,
                                    updated_at = NOW()
                                    WHERE order_id = ?";
                
                $update_order_stmt = mysqli_prepare($connection, $update_order_sql);
                if ($update_order_stmt) {
                    mysqli_stmt_bind_param($update_order_stmt, "si", $normalized_payment_method, $order_id_to_update);
                    mysqli_stmt_execute($update_order_stmt);
                    mysqli_stmt_close($update_order_stmt);
                }
            } elseif (strtolower($normalized_payment_method) === 'credit') {
                // For Credit payments, ensure order status is "Bill Generated" (not "Credit")
                // Also update payment_mode in orders table to reflect Credit
                $update_order_sql = "UPDATE orders SET 
                                    order_status = 'Bill Generated',
                                    payment_mode = ?,
                                    updated_at = NOW()
                                    WHERE order_id = ?";
                
                $update_order_stmt = mysqli_prepare($connection, $update_order_sql);
                if ($update_order_stmt) {
                    mysqli_stmt_bind_param($update_order_stmt, "si", $normalized_payment_method, $order_id_to_update);
                    mysqli_stmt_execute($update_order_stmt);
                    mysqli_stmt_close($update_order_stmt);
                }
                
                // Update table status to "Available" for credit payments
                $get_order_sql = "SELECT table_id, branch_id, terminal, order_type FROM orders WHERE order_id = ?";
                $get_order_stmt = mysqli_prepare($connection, $get_order_sql);
                if ($get_order_stmt) {
                    mysqli_stmt_bind_param($get_order_stmt, "i", $order_id_to_update);
                    mysqli_stmt_execute($get_order_stmt);
                    $order_result = mysqli_stmt_get_result($get_order_stmt);
                    $order_data = mysqli_fetch_assoc($order_result);
                    mysqli_stmt_close($get_order_stmt);
                    
                    // Update table status to "Available" if it's a Dine In order with a table
                    if ($order_data && isset($order_data['order_type']) && $order_data['order_type'] === 'Dine In' && 
                        isset($order_data['table_id']) && $order_data['table_id'] > 0) {
                        $table_id = intval($order_data['table_id']);
                        $branch_id = intval($order_data['branch_id']);
                        $terminal = intval($order_data['terminal']);
                        
                        $update_table_sql = "UPDATE tables 
                                            SET status = 'Available' 
                                            WHERE table_id = ? AND branch_id = ? AND terminal = ?";
                        $update_table_stmt = mysqli_prepare($connection, $update_table_sql);
                        if ($update_table_stmt) {
                            mysqli_stmt_bind_param($update_table_stmt, "iii", $table_id, $branch_id, $terminal);
                            mysqli_stmt_execute($update_table_stmt);
                            mysqli_stmt_close($update_table_stmt);
                        }
                    }
                }
            } else {
                // For other payment methods, just update payment_mode
                // Keep order status as is (likely "Bill Generated")
                $update_order_sql = "UPDATE orders SET 
                                    payment_mode = ?,
                                    updated_at = NOW()
                                    WHERE order_id = ?";
                
                $update_order_stmt = mysqli_prepare($connection, $update_order_sql);
                if ($update_order_stmt) {
                    mysqli_stmt_bind_param($update_order_stmt, "si", $normalized_payment_method, $order_id_to_update);
                    mysqli_stmt_execute($update_order_stmt);
                    mysqli_stmt_close($update_order_stmt);
                }
            }
        }
        
        // Get updated bill with customer information
        $get_sql = "SELECT 
                        bill.*,
                        o.customer_id,
                        c.name AS customer_name,
                        c.phone AS customer_phone,
                        c.email AS customer_email,
                        c.balance AS customer_balance
                    FROM bills bill
                    LEFT JOIN orders o ON bill.order_id = o.order_id
                    LEFT JOIN customers c ON o.customer_id = c.customer_id
                    WHERE bill.bill_id = ?";
        $get_stmt = mysqli_prepare($connection, $get_sql);
        mysqli_stmt_bind_param($get_stmt, "i", $actual_bill_id);
        mysqli_stmt_execute($get_stmt);
        $result = mysqli_stmt_get_result($get_stmt);
        $updated_bill = mysqli_fetch_assoc($result);
        mysqli_stmt_close($get_stmt);
        
        // Add is_credit flag if bill exists
        if ($updated_bill) {
            $customer_id = isset($updated_bill['customer_id']) && $updated_bill['customer_id'] > 0 ? intval($updated_bill['customer_id']) : null;
            $payment_status_lower = strtolower(trim($updated_bill['payment_status'] ?? ''));
            $payment_method_lower = strtolower(trim($updated_bill['payment_method'] ?? ''));
            
            $is_credit = false;
            if ($payment_method_lower === 'credit' || $payment_method_lower === 'cred') {
                $is_credit = true;
            } elseif ($payment_status_lower === 'unpaid' && $customer_id !== null && $customer_id > 0) {
                $is_credit = true;
            }
            
            // Add customer field name variations
            $customer_name = $updated_bill['customer_name'] ?? null;
            $customer_phone = $updated_bill['customer_phone'] ?? null;
            $customer_email = $updated_bill['customer_email'] ?? null;
            $customer_balance = isset($updated_bill['customer_balance']) ? floatval($updated_bill['customer_balance']) : null;
            
            $updated_bill['customerName'] = $customer_name; // camelCase variant
            $updated_bill['customerPhone'] = $customer_phone; // camelCase variant
            $updated_bill['customerEmail'] = $customer_email; // camelCase variant
            $updated_bill['customerBalance'] = $customer_balance; // camelCase variant
            
            // Customer object for easy access
            $updated_bill['customer'] = $customer_id ? [
                'id' => $customer_id,
                'customer_id' => $customer_id,
                'name' => $customer_name,
                'customer_name' => $customer_name,
                'phone' => $customer_phone,
                'customer_phone' => $customer_phone,
                'email' => $customer_email,
                'customer_email' => $customer_email,
                'balance' => $customer_balance,
                'customer_balance' => $customer_balance
            ] : null;
            
            $updated_bill['is_credit'] = $is_credit;
        }
        
        // Fetch order items for the bill (using order_items table)
        $order_items = [];
        if ($updated_bill && isset($updated_bill['order_id'])) {
            $order_id_for_items = intval($updated_bill['order_id']);
            $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
            $has_order_items_table = ($check_table_items && mysqli_num_rows($check_table_items) > 0);
            
            if ($has_order_items_table) {
                $items_sql = "SELECT order_items.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM order_items 
                             LEFT JOIN dishes ON dishes.dish_id = order_items.dish_id 
                             WHERE order_items.order_id = ?
                             ORDER BY order_items.item_id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "i", $order_id_for_items);
                    mysqli_stmt_execute($items_stmt);
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                    mysqli_stmt_close($items_stmt);
                }
            } else {
                // Fallback to orderdetails table (legacy)
                $orderid_str = 'ORD-' . $order_id_for_items;
                $items_sql = "SELECT orderdetails.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM orderdetails 
                             LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                             WHERE orderdetails.orderid = ?
                             ORDER BY orderdetails.id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "s", $orderid_str);
                    mysqli_stmt_execute($items_stmt);
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
        }
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Payment status updated successfully",
            "data" => [
                "bill" => $updated_bill,
                "bill_id" => $actual_bill_id,
                "payment_status" => $updated_bill['payment_status'],
                "order_items" => $order_items,
                "items_count" => count($order_items)
            ]
        ]);
        exit();
    }
    
    // ============================================
    // CREATE BILL (POST without bill_id, with total_amount)
    // This handles both new bill creation and bill generation
    // ============================================
    if ($method === 'POST' && empty($input['bill_id']) && isset($input['total_amount'])) {
        // Validate required fields
        $order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
        $total_amount = isset($input['total_amount']) ? floatval($input['total_amount']) : 0;
        $service_charge = isset($input['service_charge']) ? floatval($input['service_charge']) : 0.00;
        $discount = isset($input['discount']) ? floatval($input['discount']) : (isset($input['discount_amount']) ? floatval($input['discount_amount']) : 0.00);
        $grand_total = isset($input['grand_total']) ? floatval($input['grand_total']) : (isset($input['net_total_amount']) ? floatval($input['net_total_amount']) : 0);
        $payment_method = $input['payment_method'] ?? ($input['payment_mode'] ?? 'Cash');
        $payment_status = $input['payment_status'] ?? 'Unpaid';
        
        // Normalize payment method (case-insensitive)
        $payment_method_lower = strtolower(trim($payment_method));
        if (in_array($payment_method_lower, ['credit', 'cred'])) {
            $payment_method = 'Credit';
        } elseif (in_array($payment_method_lower, ['card', 'debit', 'credit card'])) {
            $payment_method = 'Card';
        } elseif (in_array($payment_method_lower, ['online', 'upi', 'digital', 'netbanking'])) {
            $payment_method = 'Online';
        } elseif (in_array($payment_method_lower, ['cash'])) {
            $payment_method = 'Cash';
        } else {
            // Default to what was provided or Cash
            $payment_method = ucfirst($payment_method_lower) ?: 'Cash';
        }
        
        // If payment is Credit, set status appropriately
        if (strtolower($payment_method) === 'credit') {
            $payment_status = 'Unpaid'; // Credit bills are unpaid by default
        }
        
        // Validate payment_status enum
        if (!in_array($payment_status, ['Paid', 'Unpaid'])) {
            $payment_status = 'Unpaid';
        }
        
        // Calculate grand_total if not provided
        if ($grand_total <= 0) {
            $grand_total = max(0, $total_amount + $service_charge - $discount);
        }
        
        // Validate required fields
        if (empty($order_id) || $order_id <= 0) {
            throw new Exception("Order ID is required");
        }
        
        if ($total_amount <= 0 && $grand_total <= 0) {
            throw new Exception("Total amount is required");
        }
        
        // If total_amount is 0 but grand_total is provided, use grand_total
        if ($total_amount <= 0 && $grand_total > 0) {
            $total_amount = $grand_total - $service_charge + $discount;
        }
        
        // Verify order exists and has items before creating bill
        $check_order_sql = "SELECT order_id FROM orders WHERE order_id = ?";
        $check_order_stmt = mysqli_prepare($connection, $check_order_sql);
        if (!$check_order_stmt) {
            throw new Exception("Error checking order: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param($check_order_stmt, "i", $order_id);
        mysqli_stmt_execute($check_order_stmt);
        $order_result = mysqli_stmt_get_result($check_order_stmt);
        $order_exists = mysqli_fetch_assoc($order_result);
        mysqli_stmt_close($check_order_stmt);
        
        if (!$order_exists) {
            throw new Exception("Order not found with ID: " . $order_id);
        }
        
        // Check if order has items - check both order_items and orderdetails tables
        $has_order_items_table = false;
        $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
        if ($check_table_items && mysqli_num_rows($check_table_items) > 0) {
            $has_order_items_table = true;
        }
        
        $items_count = 0;
        $items_table_used = '';
        
        if ($has_order_items_table) {
            // Check order_items table
            $check_items_sql = "SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?";
            $check_items_stmt = mysqli_prepare($connection, $check_items_sql);
            if ($check_items_stmt) {
                mysqli_stmt_bind_param($check_items_stmt, "i", $order_id);
                mysqli_stmt_execute($check_items_stmt);
                $items_result = mysqli_stmt_get_result($check_items_stmt);
                $items_row = mysqli_fetch_assoc($items_result);
                $items_count = intval($items_row['item_count'] ?? 0);
                mysqli_stmt_close($check_items_stmt);
                $items_table_used = 'order_items';
            }
        }
        
        // If no items found in order_items, check orderdetails table
        if ($items_count <= 0) {
            $orderid_str = 'ORD-' . $order_id;
            $check_items_sql = "SELECT COUNT(*) as item_count FROM orderdetails WHERE orderid = ?";
            $check_items_stmt = mysqli_prepare($connection, $check_items_sql);
            if ($check_items_stmt) {
                mysqli_stmt_bind_param($check_items_stmt, "s", $orderid_str);
                mysqli_stmt_execute($check_items_stmt);
                $items_result = mysqli_stmt_get_result($check_items_stmt);
                $items_row = mysqli_fetch_assoc($items_result);
                $items_count = intval($items_row['item_count'] ?? 0);
                mysqli_stmt_close($check_items_stmt);
                if ($items_count > 0) {
                    $items_table_used = 'orderdetails';
                }
            }
        }
        
        // If still no items, try alternative orderid formats
        if ($items_count <= 0) {
            // Try without 'ORD-' prefix
            $check_items_sql = "SELECT COUNT(*) as item_count FROM orderdetails WHERE orderid = ?";
            $check_items_stmt = mysqli_prepare($connection, $check_items_sql);
            if ($check_items_stmt) {
                mysqli_stmt_bind_param($check_items_stmt, "s", $order_id);
                mysqli_stmt_execute($check_items_stmt);
                $items_result = mysqli_stmt_get_result($check_items_stmt);
                $items_row = mysqli_fetch_assoc($items_result);
                $items_count = intval($items_row['item_count'] ?? 0);
                mysqli_stmt_close($check_items_stmt);
                if ($items_count > 0) {
                    $items_table_used = 'orderdetails (without ORD- prefix)';
                }
            }
        }
        
        if ($items_count <= 0) {
            // Provide detailed error message for debugging
            $error_msg = "Cannot generate receipt: No order items found for order ID: $order_id. ";
            $error_msg .= "Checked tables: " . ($has_order_items_table ? "order_items, " : "") . "orderdetails. ";
            $error_msg .= "Please ensure the order has items before generating the bill.";
            throw new Exception($error_msg);
        }
        
        // Check if bill already exists for this order
        $check_sql = "SELECT bill_id FROM bills WHERE order_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $order_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $existing_bill = mysqli_fetch_assoc($result);
        mysqli_stmt_close($check_stmt);
        
        if ($existing_bill) {
            // Get full existing bill data before updating (for customer balance calculation)
            $get_existing_bill_sql = "SELECT b.*, o.customer_id 
                                     FROM bills b 
                                     LEFT JOIN orders o ON b.order_id = o.order_id 
                                     WHERE b.bill_id = ?";
            $get_existing_stmt = mysqli_prepare($connection, $get_existing_bill_sql);
            $old_bill_data = null;
            if ($get_existing_stmt) {
                mysqli_stmt_bind_param($get_existing_stmt, "i", $existing_bill['bill_id']);
                if (mysqli_stmt_execute($get_existing_stmt)) {
                    $old_bill_result = mysqli_stmt_get_result($get_existing_stmt);
                    $old_bill_data = mysqli_fetch_assoc($old_bill_result);
                }
                mysqli_stmt_close($get_existing_stmt);
            }
            
            // Update existing bill instead of creating new one
            $update_fields = ['updated_at = NOW()'];
            $update_values = [];
            $types = '';
            
            if (isset($input['total_amount'])) {
                $update_fields[] = 'total_amount = ?';
                $update_values[] = floatval($input['total_amount']);
                $types .= 'd';
            }
            if (isset($input['service_charge'])) {
                $update_fields[] = 'service_charge = ?';
                $update_values[] = floatval($input['service_charge']);
                $types .= 'd';
            }
            if (isset($input['discount'])) {
                $update_fields[] = 'discount = ?';
                $update_values[] = floatval($input['discount']);
                $types .= 'd';
            }
            if (isset($input['grand_total'])) {
                $update_fields[] = 'grand_total = ?';
                $update_values[] = floatval($input['grand_total']);
                $types .= 'd';
            }
            if (isset($input['payment_method'])) {
                $update_fields[] = 'payment_method = ?';
                $update_values[] = $input['payment_method'];
                $types .= 's';
            }
            if (isset($input['payment_status'])) {
                $update_fields[] = 'payment_status = ?';
                $update_values[] = $input['payment_status'];
                $types .= 's';
            }
            
            // Add bill_id for WHERE clause
            $update_values[] = $existing_bill['bill_id'];
            $types .= 'i';
            
            // Build SQL query
            $update_sql = "UPDATE bills SET " . implode(', ', $update_fields) . " WHERE bill_id = ?";
            
            // Verify counts match
            $placeholder_count = substr_count($update_sql, '?');
            $value_count = count($update_values);
            $type_count = strlen($types);
            
            if ($placeholder_count !== $value_count || $value_count !== $type_count) {
                error_log("Parameter mismatch in bill update - Placeholders: $placeholder_count, Values: $value_count, Types: $type_count");
                error_log("SQL: $update_sql");
                error_log("Types: $types");
                throw new Exception("Parameter count mismatch in bill update. Placeholders: $placeholder_count, Values: $value_count, Types: $type_count");
            }
            
            $update_stmt = mysqli_prepare($connection, $update_sql);
            
            if (!$update_stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($update_stmt, $types, ...$update_values);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Error updating bill: " . mysqli_error($connection));
            }
            
            mysqli_stmt_close($update_stmt);
            
            $bill_id = $existing_bill['bill_id'];
            
            // Handle customer balance update after bill update
            if ($old_bill_data) {
                // Get new bill values
                $new_payment_method = isset($input['payment_method']) ? $input['payment_method'] : ($old_bill_data['payment_method'] ?? 'Cash');
                $new_payment_status = isset($input['payment_status']) ? $input['payment_status'] : ($old_bill_data['payment_status'] ?? 'Unpaid');
                $new_grand_total = isset($input['grand_total']) ? floatval($input['grand_total']) : floatval($old_bill_data['grand_total'] ?? 0);
                
                $old_payment_method = $old_bill_data['payment_method'] ?? 'Cash';
                $old_payment_status = $old_bill_data['payment_status'] ?? 'Unpaid';
                $old_grand_total = floatval($old_bill_data['grand_total'] ?? 0);
                $old_customer_id = isset($old_bill_data['customer_id']) && $old_bill_data['customer_id'] > 0 ? intval($old_bill_data['customer_id']) : null;
                
                // Normalize payment methods
                $old_payment_method_lower = strtolower(trim($old_payment_method));
                $new_payment_method_lower = strtolower(trim($new_payment_method));
                
                // Get customer_id from order
                $customer_id = $old_customer_id;
                if (!$customer_id) {
                    $get_cust_sql = "SELECT customer_id FROM orders WHERE order_id = ?";
                    $get_cust_stmt = mysqli_prepare($connection, $get_cust_sql);
                    if ($get_cust_stmt) {
                        $order_id_for_cust = intval($old_bill_data['order_id'] ?? 0);
                        mysqli_stmt_bind_param($get_cust_stmt, "i", $order_id_for_cust);
                        if (mysqli_stmt_execute($get_cust_stmt)) {
                            $cust_result = mysqli_stmt_get_result($get_cust_stmt);
                            if ($cust_row = mysqli_fetch_assoc($cust_result)) {
                                $customer_id = isset($cust_row['customer_id']) && $cust_row['customer_id'] > 0 ? intval($cust_row['customer_id']) : null;
                            }
                        }
                        mysqli_stmt_close($get_cust_stmt);
                    }
                }
                
                $old_was_credit = isCreditBill($old_payment_method, $old_payment_status, $old_customer_id);
                $new_is_credit = isCreditBill($new_payment_method, $new_payment_status, $customer_id);
                
                if ($old_was_credit && !$new_is_credit && $customer_id) {
                    // Credit bill changed to non-credit (e.g., paid) - subtract old amount
                    updateCustomerBalance($connection, $customer_id, $old_grand_total, 'subtract');
                } elseif (!$old_was_credit && $new_is_credit && $customer_id) {
                    // Non-credit bill changed to credit - add new amount
                    updateCustomerBalance($connection, $customer_id, $new_grand_total, 'add');
                } elseif ($old_was_credit && $new_is_credit && $customer_id) {
                    // Both old and new are credit - adjust balance if amount changed
                    if (abs($new_grand_total - $old_grand_total) > 0.01) {
                        // Amount changed - adjust balance
                        if ($new_grand_total > $old_grand_total) {
                            // Amount increased - add difference
                            $diff = $new_grand_total - $old_grand_total;
                            updateCustomerBalance($connection, $customer_id, $diff, 'add');
                        } else {
                            // Amount decreased - subtract difference
                            $diff = $old_grand_total - $new_grand_total;
                            updateCustomerBalance($connection, $customer_id, $diff, 'subtract');
                        }
                    }
                }
            }
        } else {
            // Create new bill
            $sql = "INSERT INTO bills (
                    order_id, 
                    total_amount, 
                    service_charge, 
                    discount, 
                    grand_total, 
                    payment_method, 
                    payment_status
                  ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, "iddddss", 
                $order_id,
                $total_amount,
                $service_charge,
                $discount,
                $grand_total,
                $payment_method,
                $payment_status
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error creating bill: " . mysqli_error($connection));
            }
            
            $bill_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt);
            
            // Update customer balance if this is a credit bill (NEW BILL)
            if (isCreditBill($payment_method, $payment_status)) {
                // Get customer_id from order
                $get_customer_sql = "SELECT customer_id FROM orders WHERE order_id = ?";
                $get_customer_stmt = mysqli_prepare($connection, $get_customer_sql);
                if ($get_customer_stmt) {
                    mysqli_stmt_bind_param($get_customer_stmt, "i", $order_id);
                    if (mysqli_stmt_execute($get_customer_stmt)) {
                        $customer_result = mysqli_stmt_get_result($get_customer_stmt);
                        if ($customer_row = mysqli_fetch_assoc($customer_result)) {
                            $customer_id = isset($customer_row['customer_id']) && $customer_row['customer_id'] > 0 ? intval($customer_row['customer_id']) : null;
                            if ($customer_id) {
                                // Add credit amount to customer balance
                                updateCustomerBalance($connection, $customer_id, $grand_total, 'add');
                            }
                        }
                    }
                    mysqli_stmt_close($get_customer_stmt);
                }
            }
        }
        
        // Update order status - always use "Bill Generated" (Credit is indicated via payment_method)
        // Frontend validation doesn't allow "Credit" as order_status, so we use payment_method to indicate credit
        $order_status = 'Bill Generated';
        
        $update_order_sql = "UPDATE orders SET 
                            order_status = ?,
                            service_charge = ?,
                            discount_amount = ?,
                            net_total_amount = ?,
                            payment_mode = ?,
                            updated_at = NOW()
                            WHERE order_id = ?";
        
        $update_stmt = mysqli_prepare($connection, $update_order_sql);
        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "sdddsi",
                $order_status,
                $service_charge,
                $discount,
                $grand_total,
                $payment_method,
                $order_id
            );
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
        
        // If payment is Credit, update table status to "Available"
        if (strtolower($payment_method) === 'credit') {
            // Get order details to find table_id, branch_id, and terminal
            $get_order_sql = "SELECT table_id, branch_id, terminal, order_type FROM orders WHERE order_id = ?";
            $get_order_stmt = mysqli_prepare($connection, $get_order_sql);
            if ($get_order_stmt) {
                mysqli_stmt_bind_param($get_order_stmt, "i", $order_id);
                mysqli_stmt_execute($get_order_stmt);
                $order_result = mysqli_stmt_get_result($get_order_stmt);
                $order_data = mysqli_fetch_assoc($order_result);
                mysqli_stmt_close($get_order_stmt);
                
                // Update table status to "Available" if it's a Dine In order with a table
                if ($order_data && isset($order_data['order_type']) && $order_data['order_type'] === 'Dine In' && 
                    isset($order_data['table_id']) && $order_data['table_id'] > 0) {
                    $table_id = intval($order_data['table_id']);
                    $branch_id = intval($order_data['branch_id']);
                    $terminal = intval($order_data['terminal']);
                    
                    $update_table_sql = "UPDATE tables 
                                        SET status = 'Available' 
                                        WHERE table_id = ? AND branch_id = ? AND terminal = ?";
                    $update_table_stmt = mysqli_prepare($connection, $update_table_sql);
                    if ($update_table_stmt) {
                        mysqli_stmt_bind_param($update_table_stmt, "iii", $table_id, $branch_id, $terminal);
                        mysqli_stmt_execute($update_table_stmt);
                        mysqli_stmt_close($update_table_stmt);
                    }
                }
            }
        }
        
        // Get created/updated bill with customer information
        $get_bill_sql = "SELECT 
                            bill.*,
                            o.customer_id,
                            c.name AS customer_name,
                            c.phone AS customer_phone,
                            c.email AS customer_email,
                            c.balance AS customer_balance
                        FROM bills bill
                        LEFT JOIN orders o ON bill.order_id = o.order_id
                        LEFT JOIN customers c ON o.customer_id = c.customer_id
                        WHERE bill.bill_id = ?";
        $get_stmt = mysqli_prepare($connection, $get_bill_sql);
        mysqli_stmt_bind_param($get_stmt, "i", $bill_id);
        mysqli_stmt_execute($get_stmt);
        $result = mysqli_stmt_get_result($get_stmt);
        $bill = mysqli_fetch_assoc($result);
        mysqli_stmt_close($get_stmt);
        
        if (!$bill) {
            $bill = [
                "bill_id" => $bill_id,
                "order_id" => $order_id,
                "total_amount" => $total_amount,
                "service_charge" => $service_charge,
                "discount" => $discount,
                "grand_total" => $grand_total,
                "payment_method" => $payment_method,
                "payment_status" => $payment_status,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            ];
        }
        
        // Add customer information and is_credit flag
        $customer_id = isset($bill['customer_id']) && $bill['customer_id'] > 0 ? intval($bill['customer_id']) : null;
        $payment_status_lower = strtolower(trim($bill['payment_status'] ?? ''));
        $payment_method_lower = strtolower(trim($bill['payment_method'] ?? ''));
        
        // Determine if this is a credit sale
        $is_credit = false;
        if ($payment_method_lower === 'credit' || $payment_method_lower === 'cred') {
            $is_credit = true;
        } elseif ($payment_status_lower === 'unpaid' && $customer_id !== null && $customer_id > 0) {
            $is_credit = true;
        }
        
        // Add customer fields and is_credit to bill with multiple field name variations
        $bill['customer_id'] = $customer_id;
        $customer_name = $bill['customer_name'] ?? null;
        $customer_phone = $bill['customer_phone'] ?? null;
        $customer_email = $bill['customer_email'] ?? null;
        $customer_balance = isset($bill['customer_balance']) ? floatval($bill['customer_balance']) : null;
        
        // Multiple field name variations for frontend compatibility
        $bill['customer_name'] = $customer_name;
        $bill['customerName'] = $customer_name; // camelCase variant
        $bill['customer_phone'] = $customer_phone;
        $bill['customerPhone'] = $customer_phone; // camelCase variant
        $bill['customer_email'] = $customer_email;
        $bill['customerEmail'] = $customer_email; // camelCase variant
        $bill['customer_balance'] = $customer_balance;
        $bill['customerBalance'] = $customer_balance; // camelCase variant
        
        // Customer object for easy access
        $bill['customer'] = $customer_id ? [
            'id' => $customer_id,
            'customer_id' => $customer_id,
            'name' => $customer_name,
            'customer_name' => $customer_name,
            'phone' => $customer_phone,
            'customer_phone' => $customer_phone,
            'email' => $customer_email,
            'customer_email' => $customer_email,
            'balance' => $customer_balance,
            'customer_balance' => $customer_balance
        ] : null;
        
        $bill['is_credit'] = $is_credit;
        
        // Fetch order items for the bill - try both order_items and orderdetails tables
        $order_items = [];
        
        if ($has_order_items_table) {
            // Check if is_cancel column exists before using it
            $check_cancel_col = mysqli_query($connection, "SHOW COLUMNS FROM order_items LIKE 'is_cancel'");
            $has_is_cancel_col = ($check_cancel_col && mysqli_num_rows($check_cancel_col) > 0);
            $cancel_condition = $has_is_cancel_col ? "AND (order_items.is_cancel IS NULL OR order_items.is_cancel = 0)" : "";
            
            // Use order_items table - only non-cancelled items if column exists
            $items_sql = "SELECT order_items.*, 
                         COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                         COALESCE(dishes.description, '') as description
                         FROM order_items 
                         LEFT JOIN dishes ON dishes.dish_id = order_items.dish_id 
                         WHERE order_items.order_id = ? $cancel_condition
                         ORDER BY order_items.item_id ASC";
            $items_stmt = mysqli_prepare($connection, $items_sql);
            if ($items_stmt) {
                mysqli_stmt_bind_param($items_stmt, "i", $order_id);
                if (mysqli_stmt_execute($items_stmt)) {
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                }
                mysqli_stmt_close($items_stmt);
            }
        }
        
        // If no items found in order_items, try orderdetails table (legacy)
        if (empty($order_items)) {
            // Check if is_cancel column exists before using it
            $check_cancel_col_od = mysqli_query($connection, "SHOW COLUMNS FROM orderdetails LIKE 'is_cancel'");
            $has_is_cancel_col_od = ($check_cancel_col_od && mysqli_num_rows($check_cancel_col_od) > 0);
            $cancel_condition_od = $has_is_cancel_col_od ? "AND (orderdetails.is_cancel IS NULL OR orderdetails.is_cancel = 0)" : "";
            
            $orderid_str = 'ORD-' . $order_id;
            $items_sql = "SELECT orderdetails.*, 
                         COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                         COALESCE(dishes.description, '') as description
                         FROM orderdetails 
                         LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                         WHERE orderdetails.orderid = ? $cancel_condition_od
                         ORDER BY orderdetails.id ASC";
            $items_stmt = mysqli_prepare($connection, $items_sql);
            if ($items_stmt) {
                mysqli_stmt_bind_param($items_stmt, "s", $orderid_str);
                if (mysqli_stmt_execute($items_stmt)) {
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                }
                mysqli_stmt_close($items_stmt);
            }
        }
        
        // Also try without ORD- prefix if still no items
        if (empty($order_items)) {
            $cancel_condition_od2 = isset($has_is_cancel_col_od) && $has_is_cancel_col_od ? "AND (orderdetails.is_cancel IS NULL OR orderdetails.is_cancel = 0)" : "";
            
            $items_sql = "SELECT orderdetails.*, 
                         COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                         COALESCE(dishes.description, '') as description
                         FROM orderdetails 
                         LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                         WHERE orderdetails.orderid = ? $cancel_condition_od2
                         ORDER BY orderdetails.id ASC";
            $items_stmt = mysqli_prepare($connection, $items_sql);
            if ($items_stmt) {
                mysqli_stmt_bind_param($items_stmt, "s", $order_id);
                if (mysqli_stmt_execute($items_stmt)) {
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                }
                mysqli_stmt_close($items_stmt);
            }
        }
        
        // Always include items array (even if empty, but log warning)
        if (empty($order_items)) {
            error_log("WARNING: Bill created for order $order_id but no items found!");
        }
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "message" => "Bill created successfully",
            "data" => [
                "bill" => $bill,
                "bill_id" => $bill_id,
                "order_items" => $order_items,
                "items_count" => count($order_items)
            ]
        ]);
        exit();
    }
    
    // ============================================
    // UPDATE BILL BY BILL_ID (POST with bill_id or PUT)
    // ============================================
    if (($method === 'POST' && !empty($input['bill_id'])) || $method === 'PUT') {
        $bill_id = isset($input['bill_id']) ? intval($input['bill_id']) : 0;
        
        if (empty($bill_id) || $bill_id <= 0) {
            throw new Exception("Bill ID is required for update");
        }
        
        // Get existing bill with customer_id
        $check_sql = "SELECT b.*, o.customer_id 
                     FROM bills b 
                     LEFT JOIN orders o ON b.order_id = o.order_id 
                     WHERE b.bill_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $bill_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $existing_bill = mysqli_fetch_assoc($result);
        mysqli_stmt_close($check_stmt);
        
        if (!$existing_bill) {
            throw new Exception("Bill not found");
        }
        
        // Store old bill data for balance calculation
        $old_payment_method = $existing_bill['payment_method'] ?? 'Cash';
        $old_payment_status = $existing_bill['payment_status'] ?? 'Unpaid';
        $old_grand_total = floatval($existing_bill['grand_total'] ?? 0);
        $old_customer_id = isset($existing_bill['customer_id']) && $existing_bill['customer_id'] > 0 ? intval($existing_bill['customer_id']) : null;
        
        // Prepare update fields
        $total_amount = isset($input['total_amount']) ? floatval($input['total_amount']) : $existing_bill['total_amount'];
        $service_charge = isset($input['service_charge']) ? floatval($input['service_charge']) : $existing_bill['service_charge'];
        $discount = isset($input['discount']) ? floatval($input['discount']) : (isset($input['discount_amount']) ? floatval($input['discount_amount']) : $existing_bill['discount']);
        $grand_total = isset($input['grand_total']) ? floatval($input['grand_total']) : (isset($input['net_total_amount']) ? floatval($input['net_total_amount']) : $existing_bill['grand_total']);
        
        // Handle payment_method - check for empty strings too
        $payment_method = null;
        if (isset($input['payment_method']) && trim($input['payment_method']) !== '') {
            $payment_method = trim($input['payment_method']);
        } elseif (isset($input['payment_mode']) && trim($input['payment_mode']) !== '') {
            $payment_method = trim($input['payment_mode']);
        } elseif (isset($existing_bill['payment_method']) && trim($existing_bill['payment_method']) !== '') {
            $payment_method = trim($existing_bill['payment_method']);
        } else {
            $payment_method = 'Cash';
        }
        
        // Handle payment_status - check for empty strings too
        $payment_status = null;
        if (isset($input['payment_status']) && trim($input['payment_status']) !== '') {
            $payment_status = trim($input['payment_status']);
        } elseif (isset($existing_bill['payment_status']) && trim($existing_bill['payment_status']) !== '') {
            $payment_status = trim($existing_bill['payment_status']);
        } else {
            $payment_status = 'Unpaid';
        }
        
        // Normalize payment method (case-insensitive)
        $payment_method_lower = strtolower(trim($payment_method));
        if (in_array($payment_method_lower, ['credit', 'cred'])) {
            $payment_method = 'Credit';
            // Credit bills are unpaid by default
            if (!isset($input['payment_status']) || trim($input['payment_status']) === '') {
                $payment_status = 'Unpaid';
            }
        } elseif (in_array($payment_method_lower, ['card', 'debit', 'credit card'])) {
            $payment_method = 'Card';
        } elseif (in_array($payment_method_lower, ['online', 'upi', 'digital', 'netbanking'])) {
            $payment_method = 'Online';
        } elseif (in_array($payment_method_lower, ['cash'])) {
            $payment_method = 'Cash';
        } else {
            // Keep existing if not recognized, or default to Cash
            $payment_method = $existing_bill['payment_method'] ?? 'Cash';
        }
        
        // Validate payment_status enum
        if (!in_array($payment_status, ['Paid', 'Unpaid'])) {
            $payment_status = 'Unpaid';
        }
        
        // Calculate grand_total if not provided
        if ($grand_total <= 0) {
            $grand_total = max(0, $total_amount + $service_charge - $discount);
        }
        
        // Update bill - build dynamically to support optional fields
        $update_fields = [];
        $update_values = [];
        $types = '';
        
        // Always update these fields
        $update_fields[] = 'total_amount = ?';
        $update_values[] = $total_amount;
        $types .= 'd';
        
        $update_fields[] = 'service_charge = ?';
        $update_values[] = $service_charge;
        $types .= 'd';
        
        $update_fields[] = 'discount = ?';
        $update_values[] = $discount;
        $types .= 'd';
        
        $update_fields[] = 'grand_total = ?';
        $update_values[] = $grand_total;
        $types .= 'd';
        
        $update_fields[] = 'payment_method = ?';
        $update_values[] = $payment_method;
        $types .= 's';
        
        $update_fields[] = 'payment_status = ?';
        $update_values[] = $payment_status;
        $types .= 's';
        
        // Note: cash_received and change are not stored in bills table
        // They are calculated/displayed in the frontend only
        
        // Add updated_at (no placeholder) and WHERE clause
        $update_fields[] = 'updated_at = NOW()';
        $update_values[] = $bill_id;
        $types .= 'i';
        
        $sql = "UPDATE bills SET " . implode(', ', $update_fields) . " WHERE bill_id = ?";
        
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        // Verify counts match
        $placeholder_count = substr_count($sql, '?');
        $value_count = count($update_values);
        $type_count = strlen($types);
        
        if ($placeholder_count !== $value_count || $value_count !== $type_count) {
            error_log("Parameter mismatch in bill update by bill_id - Placeholders: $placeholder_count, Values: $value_count, Types: $type_count");
            error_log("SQL: $sql");
            error_log("Types: $types");
            error_log("Values: " . print_r($update_values, true));
            throw new Exception("Parameter count mismatch in bill update. Placeholders: $placeholder_count, Values: $value_count, Types: $type_count");
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$update_values);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating bill: " . mysqli_error($connection));
        }
        
        mysqli_stmt_close($stmt);
        
        // Handle customer balance update after bill update by bill_id
        // Get customer_id from order
        $customer_id = $old_customer_id;
        if ($existing_bill && isset($existing_bill['order_id']) && !$customer_id) {
            $get_cust_sql = "SELECT customer_id FROM orders WHERE order_id = ?";
            $get_cust_stmt = mysqli_prepare($connection, $get_cust_sql);
            if ($get_cust_stmt) {
                $order_id_for_cust = intval($existing_bill['order_id'] ?? 0);
                mysqli_stmt_bind_param($get_cust_stmt, "i", $order_id_for_cust);
                if (mysqli_stmt_execute($get_cust_stmt)) {
                    $cust_result = mysqli_stmt_get_result($get_cust_stmt);
                    if ($cust_row = mysqli_fetch_assoc($cust_result)) {
                        $customer_id = isset($cust_row['customer_id']) && $cust_row['customer_id'] > 0 ? intval($cust_row['customer_id']) : null;
                    }
                }
                mysqli_stmt_close($get_cust_stmt);
            }
        }
        
        // Compare old vs new bill data and update customer balance
        if ($customer_id) {
            $old_was_credit = isCreditBill($old_payment_method, $old_payment_status, $old_customer_id);
            $new_is_credit = isCreditBill($payment_method, $payment_status, $customer_id);
            
            if ($old_was_credit && !$new_is_credit) {
                // Credit bill changed to non-credit (e.g., paid) - subtract old amount
                updateCustomerBalance($connection, $customer_id, $old_grand_total, 'subtract');
            } elseif (!$old_was_credit && $new_is_credit) {
                // Non-credit bill changed to credit - add new amount
                updateCustomerBalance($connection, $customer_id, $grand_total, 'add');
            } elseif ($old_was_credit && $new_is_credit) {
                // Both old and new are credit - adjust balance if amount changed
                if (abs($grand_total - $old_grand_total) > 0.01) {
                    // Amount changed - adjust balance
                    if ($grand_total > $old_grand_total) {
                        // Amount increased - add difference
                        $diff = $grand_total - $old_grand_total;
                        updateCustomerBalance($connection, $customer_id, $diff, 'add');
                    } else {
                        // Amount decreased - subtract difference
                        $diff = $old_grand_total - $grand_total;
                        updateCustomerBalance($connection, $customer_id, $diff, 'subtract');
                    }
                }
            }
        }
        
        // Update order based on payment status and method
        if ($existing_bill && isset($existing_bill['order_id'])) {
            $order_id = $existing_bill['order_id'];
            
            // If payment_status is "Paid", update order status to "Complete"
            if ($payment_status === 'Paid') {
                $update_order_sql = "UPDATE orders SET 
                                    order_status = 'Complete',
                                    payment_mode = ?,
                                    updated_at = NOW()
                                    WHERE order_id = ?";
                
                $update_stmt = mysqli_prepare($connection, $update_order_sql);
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "si", $payment_method, $order_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
            } elseif (strtolower($payment_method) === 'credit') {
                // For Credit payments, ensure order status is "Bill Generated" (not "Credit")
                // Also update payment_mode in orders table to reflect Credit
                $update_order_sql = "UPDATE orders SET 
                                    order_status = 'Bill Generated',
                                    payment_mode = ?,
                                    updated_at = NOW()
                                    WHERE order_id = ?";
                
                $update_stmt = mysqli_prepare($connection, $update_order_sql);
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "si", $payment_method, $order_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
                
                // Update table status to "Available" for credit payments
                $get_order_sql = "SELECT table_id, branch_id, terminal, order_type FROM orders WHERE order_id = ?";
                $get_order_stmt = mysqli_prepare($connection, $get_order_sql);
                if ($get_order_stmt) {
                    mysqli_stmt_bind_param($get_order_stmt, "i", $order_id);
                    mysqli_stmt_execute($get_order_stmt);
                    $order_result = mysqli_stmt_get_result($get_order_stmt);
                    $order_data = mysqli_fetch_assoc($order_result);
                    mysqli_stmt_close($get_order_stmt);
                    
                    // Update table status to "Available" if it's a Dine In order with a table
                    if ($order_data && isset($order_data['order_type']) && $order_data['order_type'] === 'Dine In' && 
                        isset($order_data['table_id']) && $order_data['table_id'] > 0) {
                        $table_id = intval($order_data['table_id']);
                        $branch_id = intval($order_data['branch_id']);
                        $terminal = intval($order_data['terminal']);
                        
                        $update_table_sql = "UPDATE tables 
                                            SET status = 'Available' 
                                            WHERE table_id = ? AND branch_id = ? AND terminal = ?";
                        $update_table_stmt = mysqli_prepare($connection, $update_table_sql);
                        if ($update_table_stmt) {
                            mysqli_stmt_bind_param($update_table_stmt, "iii", $table_id, $branch_id, $terminal);
                            mysqli_stmt_execute($update_table_stmt);
                            mysqli_stmt_close($update_table_stmt);
                        }
                    }
                }
            } else {
                // For other payment methods (Cash, Card, Online), update payment_mode only
                // Keep order status as is (likely "Bill Generated")
                $update_order_sql = "UPDATE orders SET 
                                    payment_mode = ?,
                                    updated_at = NOW()
                                    WHERE order_id = ?";
                
                $update_stmt = mysqli_prepare($connection, $update_order_sql);
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "si", $payment_method, $order_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
            }
        }
        
        // Get updated bill
        $get_bill_sql = "SELECT * FROM bills WHERE bill_id = ?";
        $get_stmt = mysqli_prepare($connection, $get_bill_sql);
        mysqli_stmt_bind_param($get_stmt, "i", $bill_id);
        mysqli_stmt_execute($get_stmt);
        $result = mysqli_stmt_get_result($get_stmt);
        $bill = mysqli_fetch_assoc($result);
        mysqli_stmt_close($get_stmt);
        
        // Fetch order items for the bill - try both order_items and orderdetails tables
        $order_items = [];
        if ($bill && isset($bill['order_id'])) {
            $order_id_for_items = intval($bill['order_id']);
            $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
            $has_order_items_table = ($check_table_items && mysqli_num_rows($check_table_items) > 0);
            
            if ($has_order_items_table) {
                // Check if is_cancel column exists before using it
                $check_cancel_col = mysqli_query($connection, "SHOW COLUMNS FROM order_items LIKE 'is_cancel'");
                $has_is_cancel_col = ($check_cancel_col && mysqli_num_rows($check_cancel_col) > 0);
                $cancel_condition = $has_is_cancel_col ? "AND (order_items.is_cancel IS NULL OR order_items.is_cancel = 0)" : "";
                
                // Use order_items table - only non-cancelled items if column exists
                $items_sql = "SELECT order_items.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM order_items 
                             LEFT JOIN dishes ON dishes.dish_id = order_items.dish_id 
                             WHERE order_items.order_id = ? $cancel_condition
                             ORDER BY order_items.item_id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "i", $order_id_for_items);
                    if (mysqli_stmt_execute($items_stmt)) {
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        while ($item = mysqli_fetch_assoc($items_result)) {
                            $order_items[] = $item;
                        }
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
            
            // If no items found in order_items, try orderdetails table (legacy)
            if (empty($order_items)) {
                // Check if is_cancel column exists before using it
                $check_cancel_col_od = mysqli_query($connection, "SHOW COLUMNS FROM orderdetails LIKE 'is_cancel'");
                $has_is_cancel_col_od = ($check_cancel_col_od && mysqli_num_rows($check_cancel_col_od) > 0);
                $cancel_condition_od = $has_is_cancel_col_od ? "AND (orderdetails.is_cancel IS NULL OR orderdetails.is_cancel = 0)" : "";
                
                $orderid_str = 'ORD-' . $order_id_for_items;
                $items_sql = "SELECT orderdetails.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM orderdetails 
                             LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                             WHERE orderdetails.orderid = ? $cancel_condition_od
                             ORDER BY orderdetails.id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "s", $orderid_str);
                    if (mysqli_stmt_execute($items_stmt)) {
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        while ($item = mysqli_fetch_assoc($items_result)) {
                            $order_items[] = $item;
                        }
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
            
            // Also try without ORD- prefix if still no items
            if (empty($order_items)) {
                $cancel_condition_od2 = isset($has_is_cancel_col_od) && $has_is_cancel_col_od ? "AND (orderdetails.is_cancel IS NULL OR orderdetails.is_cancel = 0)" : "";
                
                $items_sql = "SELECT orderdetails.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM orderdetails 
                             LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                             WHERE orderdetails.orderid = ? $cancel_condition_od2
                             ORDER BY orderdetails.id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "s", $order_id_for_items);
                    if (mysqli_stmt_execute($items_stmt)) {
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        while ($item = mysqli_fetch_assoc($items_result)) {
                            $order_items[] = $item;
                        }
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
        }
        
        // Always include items array (even if empty, but log warning)
        if (empty($order_items)) {
            error_log("WARNING: Bill updated but no items found for order " . ($bill['order_id'] ?? 'unknown'));
        }
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "message" => "Bill updated successfully",
            "data" => [
                "bill" => $bill,
                "order_items" => $order_items,
                "items_count" => count($order_items)
            ]
        ]);
        exit();
    }
    
    // ============================================
    // GET BILL BY ORDER ID (for fetching)
    // Only if no payment_status and no total_amount (pure fetch request)
    // ============================================
    if ($method === 'POST' && !empty($input['order_id']) && !$payment_status && !isset($input['total_amount'])) {
        $order_id = intval($input['order_id']);
        
        $sql = "SELECT 
                    bill.*,
                    o.customer_id,
                    c.name AS customer_name,
                    c.phone AS customer_phone,
                    c.email AS customer_email,
                    c.balance AS customer_balance
                FROM bills bill
                LEFT JOIN orders o ON bill.order_id = o.order_id
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                WHERE bill.order_id = ? 
                ORDER BY bill.bill_id DESC LIMIT 1";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $bill = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        // Add is_credit flag if bill exists
        if ($bill) {
            $customer_id = isset($bill['customer_id']) && $bill['customer_id'] > 0 ? intval($bill['customer_id']) : null;
            $payment_status_lower = strtolower(trim($bill['payment_status'] ?? ''));
            $payment_method_lower = strtolower(trim($bill['payment_method'] ?? ''));
            
            $is_credit = false;
            if ($payment_method_lower === 'credit' || $payment_method_lower === 'cred') {
                $is_credit = true;
            } elseif ($payment_status_lower === 'unpaid' && $customer_id !== null && $customer_id > 0) {
                $is_credit = true;
            }
            
            // Add customer field name variations
            $customer_name = $bill['customer_name'] ?? null;
            $customer_phone = $bill['customer_phone'] ?? null;
            $customer_email = $bill['customer_email'] ?? null;
            $customer_balance = isset($bill['customer_balance']) ? floatval($bill['customer_balance']) : null;
            
            $bill['customerName'] = $customer_name; // camelCase variant
            $bill['customerPhone'] = $customer_phone; // camelCase variant
            $bill['customerEmail'] = $customer_email; // camelCase variant
            $bill['customerBalance'] = $customer_balance; // camelCase variant
            
            // Customer object for easy access
            $bill['customer'] = $customer_id ? [
                'id' => $customer_id,
                'customer_id' => $customer_id,
                'name' => $customer_name,
                'customer_name' => $customer_name,
                'phone' => $customer_phone,
                'customer_phone' => $customer_phone,
                'email' => $customer_email,
                'customer_email' => $customer_email,
                'balance' => $customer_balance,
                'customer_balance' => $customer_balance
            ] : null;
            
            $bill['is_credit'] = $is_credit;
        }
        
        // Fetch order items if bill exists - try both order_items and orderdetails tables
        $order_items = [];
        if ($bill) {
            $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
            $has_order_items_table = ($check_table_items && mysqli_num_rows($check_table_items) > 0);
            
            if ($has_order_items_table) {
                // Check if is_cancel column exists before using it
                $check_cancel_col = mysqli_query($connection, "SHOW COLUMNS FROM order_items LIKE 'is_cancel'");
                $has_is_cancel_col = ($check_cancel_col && mysqli_num_rows($check_cancel_col) > 0);
                $cancel_condition = $has_is_cancel_col ? "AND (order_items.is_cancel IS NULL OR order_items.is_cancel = 0)" : "";
                
                // Use order_items table - only non-cancelled items if column exists
                $items_sql = "SELECT order_items.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM order_items 
                             LEFT JOIN dishes ON dishes.dish_id = order_items.dish_id 
                             WHERE order_items.order_id = ? $cancel_condition
                             ORDER BY order_items.item_id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "i", $order_id);
                    if (mysqli_stmt_execute($items_stmt)) {
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        while ($item = mysqli_fetch_assoc($items_result)) {
                            $order_items[] = $item;
                        }
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
            
            // If no items found in order_items, try orderdetails table (legacy)
            if (empty($order_items)) {
                // Check if is_cancel column exists before using it
                $check_cancel_col_od = mysqli_query($connection, "SHOW COLUMNS FROM orderdetails LIKE 'is_cancel'");
                $has_is_cancel_col_od = ($check_cancel_col_od && mysqli_num_rows($check_cancel_col_od) > 0);
                $cancel_condition_od = $has_is_cancel_col_od ? "AND (orderdetails.is_cancel IS NULL OR orderdetails.is_cancel = 0)" : "";
                
                $orderid_str = 'ORD-' . $order_id;
                $items_sql = "SELECT orderdetails.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM orderdetails 
                             LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                             WHERE orderdetails.orderid = ? $cancel_condition_od
                             ORDER BY orderdetails.id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "s", $orderid_str);
                    if (mysqli_stmt_execute($items_stmt)) {
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        while ($item = mysqli_fetch_assoc($items_result)) {
                            $order_items[] = $item;
                        }
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
            
            // Also try without ORD- prefix if still no items
            if (empty($order_items)) {
                $cancel_condition_od2 = isset($has_is_cancel_col_od) && $has_is_cancel_col_od ? "AND (orderdetails.is_cancel IS NULL OR orderdetails.is_cancel = 0)" : "";
                
                $items_sql = "SELECT orderdetails.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM orderdetails 
                             LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                             WHERE orderdetails.orderid = ? $cancel_condition_od2
                             ORDER BY orderdetails.id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "s", $order_id);
                    if (mysqli_stmt_execute($items_stmt)) {
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        while ($item = mysqli_fetch_assoc($items_result)) {
                            $order_items[] = $item;
                        }
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
        }
        
        // Always include items array in response (even if empty)
        if (empty($order_items)) {
            error_log("WARNING: Bill fetched for order $order_id but no items found!");
        }
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        // Always return success with has_bill flag for frontend to check
        echo json_encode([
            "success" => true,
            "has_bill" => $bill ? true : false,
            "data" => $bill ? [
                "bill" => $bill,
                "order_items" => $order_items,
                "items_count" => count($order_items)
            ] : null,
            "message" => $bill ? "Bill found" : "No bill found for this order"
        ]);
        exit();
    }
    
    // ============================================
    // GET BILL BY ID
    // ============================================
    if ($method === 'GET' && !empty($_GET['bill_id'])) {
        $bill_id = intval($_GET['bill_id']);
        
        $sql = "SELECT 
                    bill.*,
                    o.customer_id,
                    c.name AS customer_name,
                    c.phone AS customer_phone,
                    c.email AS customer_email,
                    c.balance AS customer_balance
                FROM bills bill
                LEFT JOIN orders o ON bill.order_id = o.order_id
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                WHERE bill.bill_id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $bill_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $bill = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        // Add is_credit flag if bill exists
        if ($bill) {
            $customer_id = isset($bill['customer_id']) && $bill['customer_id'] > 0 ? intval($bill['customer_id']) : null;
            $payment_status_lower = strtolower(trim($bill['payment_status'] ?? ''));
            $payment_method_lower = strtolower(trim($bill['payment_method'] ?? ''));
            
            $is_credit = false;
            if ($payment_method_lower === 'credit' || $payment_method_lower === 'cred') {
                $is_credit = true;
            } elseif ($payment_status_lower === 'unpaid' && $customer_id !== null && $customer_id > 0) {
                $is_credit = true;
            }
            
            // Add customer field name variations
            $customer_name = $bill['customer_name'] ?? null;
            $customer_phone = $bill['customer_phone'] ?? null;
            $customer_email = $bill['customer_email'] ?? null;
            $customer_balance = isset($bill['customer_balance']) ? floatval($bill['customer_balance']) : null;
            
            $bill['customerName'] = $customer_name; // camelCase variant
            $bill['customerPhone'] = $customer_phone; // camelCase variant
            $bill['customerEmail'] = $customer_email; // camelCase variant
            $bill['customerBalance'] = $customer_balance; // camelCase variant
            
            // Customer object for easy access
            $bill['customer'] = $customer_id ? [
                'id' => $customer_id,
                'customer_id' => $customer_id,
                'name' => $customer_name,
                'customer_name' => $customer_name,
                'phone' => $customer_phone,
                'customer_phone' => $customer_phone,
                'email' => $customer_email,
                'customer_email' => $customer_email,
                'balance' => $customer_balance,
                'customer_balance' => $customer_balance
            ] : null;
            
            $bill['is_credit'] = $is_credit;
        }
        
        // Fetch order items if bill exists (using order_items table)
        $order_items = [];
        if ($bill && isset($bill['order_id'])) {
            $order_id_for_items = intval($bill['order_id']);
            $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
            $has_order_items_table = ($check_table_items && mysqli_num_rows($check_table_items) > 0);
            
            if ($has_order_items_table) {
                $items_sql = "SELECT order_items.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM order_items 
                             LEFT JOIN dishes ON dishes.dish_id = order_items.dish_id 
                             WHERE order_items.order_id = ?
                             ORDER BY order_items.item_id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "i", $order_id_for_items);
                    mysqli_stmt_execute($items_stmt);
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                    mysqli_stmt_close($items_stmt);
                }
            } else {
                // Fallback to orderdetails table (legacy)
                $orderid_str = 'ORD-' . $order_id_for_items;
                $items_sql = "SELECT orderdetails.*, 
                             COALESCE(dishes.name, 'Unknown Dish') as dish_name,
                             COALESCE(dishes.description, '') as description
                             FROM orderdetails 
                             LEFT JOIN dishes ON dishes.dish_id = orderdetails.p_id 
                             WHERE orderdetails.orderid = ?
                             ORDER BY orderdetails.id ASC";
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "s", $orderid_str);
                    mysqli_stmt_execute($items_stmt);
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
        }
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        if ($bill) {
            echo json_encode([
                "success" => true,
                "data" => [
                    "bill" => $bill,
                    "order_items" => $order_items,
                    "items_count" => count($order_items)
                ]
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Bill not found"
            ]);
        }
        exit();
    }
    
    // ============================================
    // GET ALL BILLS (with optional filters)
    // Supports: customer_id, payment_status, branch_id
    // ============================================
    if ($method === 'GET' && empty($_GET['bill_id'])) {
        // Get filter parameters from query string
        $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;
        $payment_status = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : null;
        $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : null;
        
        // Build query with joins to get customer and branch information
        $sql = "SELECT 
                    b.*,
                    o.customer_id,
                    o.branch_id as order_branch_id,
                    c.name AS customer_name,
                    c.phone AS customer_phone,
                    c.email AS customer_email,
                    c.balance AS customer_balance
                FROM bills b
                LEFT JOIN orders o ON b.order_id = o.order_id
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                WHERE 1=1";
        
        $whereParams = [];
        $whereTypes = '';
        
        // Filter by customer_id if provided
        if ($customer_id !== null && $customer_id > 0) {
            $sql .= " AND o.customer_id = ?";
            $whereParams[] = $customer_id;
            $whereTypes .= 'i';
        }
        
        // Filter by payment_status if provided
        if ($payment_status !== null && $payment_status !== '') {
            if (strtolower($payment_status) === 'credit') {
                // Credit bills: payment_method='Credit' OR payment_status='Credit' 
                // OR (payment_status='Unpaid' AND customer_id IS NOT NULL)
                $sql .= " AND (
                    b.payment_method = 'Credit' 
                    OR b.payment_status = 'Credit'
                    OR (b.payment_status = 'Unpaid' AND o.customer_id IS NOT NULL AND o.customer_id > 0)
                )";
            } else {
                // Other payment statuses
                $sql .= " AND b.payment_status = ?";
                $whereParams[] = $payment_status;
                $whereTypes .= 's';
            }
        }
        
        // Filter by branch_id if provided
        if ($branch_id !== null && $branch_id > 0) {
            $sql .= " AND o.branch_id = ?";
            $whereParams[] = $branch_id;
            $whereTypes .= 'i';
        }
        
        $sql .= " ORDER BY b.created_at DESC LIMIT 100";
        
        // Prepare and execute statement
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        if (!empty($whereParams)) {
            mysqli_stmt_bind_param($stmt, $whereTypes, ...$whereParams);
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($connection);
            mysqli_stmt_close($stmt);
            throw new Exception("Error executing query: " . ($error ?: "Unknown error"));
        }
        
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result) {
            $error = mysqli_error($connection);
            mysqli_stmt_close($stmt);
            throw new Exception("Error getting result: " . ($error ?: "Unknown error"));
        }
        
        $bills = [];
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                // Add id field (alias for bill_id) and ensure customer_id and branch_id
                $bill_id = isset($row['bill_id']) ? intval($row['bill_id']) : 0;
                $row['id'] = $bill_id;
                
                if (isset($row['customer_id'])) {
                    $row['customer_id'] = intval($row['customer_id']);
                }
                if (isset($row['order_branch_id'])) {
                    $row['branch_id'] = intval($row['order_branch_id']);
                }
                
                // Determine if this is a credit bill
                $bill_customer_id = isset($row['customer_id']) && $row['customer_id'] > 0 ? intval($row['customer_id']) : null;
                $payment_status_lower = strtolower(trim($row['payment_status'] ?? ''));
                $payment_method_lower = strtolower(trim($row['payment_method'] ?? ''));
                
                $is_credit = false;
                if ($payment_method_lower === 'credit' || $payment_method_lower === 'cred') {
                    $is_credit = true;
                } elseif ($payment_status_lower === 'credit') {
                    $is_credit = true;
                } elseif ($payment_status_lower === 'unpaid' && $bill_customer_id !== null && $bill_customer_id > 0) {
                    $is_credit = true;
                }
                
                $row['is_credit'] = $is_credit;
                
                // Format date field if exists
                if (isset($row['created_at'])) {
                    $row['date'] = date('Y-m-d', strtotime($row['created_at']));
                }
                
                // Ensure grand_total is set (use net_total if grand_total not available)
                if (!isset($row['grand_total']) || empty($row['grand_total'])) {
                    $row['grand_total'] = isset($row['total_amount']) ? floatval($row['total_amount']) : 0;
                }
                if (!isset($row['net_total']) && isset($row['grand_total'])) {
                    $row['net_total'] = floatval($row['grand_total']);
                }
                
                $bills[] = $row;
            }
        }
        
        mysqli_stmt_close($stmt);
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "data" => $bills
        ]);
        exit();
    }
    
    // ============================================
    // DELETE BILL
    // ============================================
    if ($method === 'DELETE') {
        $bill_id = isset($input['bill_id']) ? intval($input['bill_id']) : 0;
        
        if (empty($bill_id) || $bill_id <= 0) {
            throw new Exception("Bill ID is required for deletion");
        }
        
        // Get bill data before deletion to update customer balance if needed
        $get_bill_sql = "SELECT b.*, o.customer_id 
                        FROM bills b 
                        LEFT JOIN orders o ON b.order_id = o.order_id 
                        WHERE b.bill_id = ?";
        $get_bill_stmt = mysqli_prepare($connection, $get_bill_sql);
        $bill_to_delete = null;
        if ($get_bill_stmt) {
            mysqli_stmt_bind_param($get_bill_stmt, "i", $bill_id);
            if (mysqli_stmt_execute($get_bill_stmt)) {
                $bill_result = mysqli_stmt_get_result($get_bill_stmt);
                $bill_to_delete = mysqli_fetch_assoc($bill_result);
            }
            mysqli_stmt_close($get_bill_stmt);
        }
        
        // Update customer balance if deleting a credit bill
        if ($bill_to_delete) {
            $delete_payment_method = $bill_to_delete['payment_method'] ?? 'Cash';
            $delete_payment_status = $bill_to_delete['payment_status'] ?? 'Unpaid';
            $delete_grand_total = floatval($bill_to_delete['grand_total'] ?? 0);
            $delete_customer_id = isset($bill_to_delete['customer_id']) && $bill_to_delete['customer_id'] > 0 ? intval($bill_to_delete['customer_id']) : null;
            
            if ($delete_customer_id && isCreditBill($delete_payment_method, $delete_payment_status, $delete_customer_id) && $delete_grand_total > 0) {
                // Subtract credit amount from customer balance before deleting
                updateCustomerBalance($connection, $delete_customer_id, $delete_grand_total, 'subtract');
            }
        }
        
        $sql = "DELETE FROM bills WHERE bill_id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $bill_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error deleting bill: " . mysqli_error($connection));
        }
        
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        if ($affected_rows > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Bill deleted successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Bill not found"
            ]);
        }
        exit();
    }
    
    // Invalid request
    throw new Exception("Invalid request method or missing parameters. Method: " . $method);
    
} catch (Exception $e) {
    error_log("Bills Management Error: " . $e->getMessage());
    error_log("Bills Management Error Trace: " . $e->getTraceAsString());
    
    // Clear buffer and return error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "data" => [
            "error" => $e->getMessage(),
            "type" => "Exception"
        ]
    ]);
    exit();
    
} catch (Error $e) {
    error_log("Bills Management Fatal Error: " . $e->getMessage());
    error_log("Bills Management Fatal Error Trace: " . $e->getTraceAsString());
    
    // Clear buffer and return error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => false,
        "message" => "Fatal error: " . $e->getMessage(),
        "data" => [
            "error" => $e->getMessage(),
            "type" => "Fatal Error"
        ]
    ]);
    exit();
}

exit();
?>

