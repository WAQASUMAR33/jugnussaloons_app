<?php
/**
 * Get Order by ID API
 * Returns order details by order ID
 * Supports both JSON and form data
 * Updated to match actual database structure (order_id, order_type, order_status)
 */
require_once 'cors_headers.php';
// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
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
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit();
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit();
}

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get input data - handle both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST; // Fallback to form data
}

// Get the orderid parameter (can be order_id or orderid string)
$orderid = $input['orderid'] ?? ($_POST['orderid'] ?? '');
$order_id = $input['order_id'] ?? ($_POST['order_id'] ?? '');

// Check connection first
if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

try {
    // Determine the numeric order_id from various input formats
    $final_order_id = null;
    
    // Priority 1: If order_id is already provided and is numeric
    if (!empty($order_id)) {
        if (is_numeric($order_id)) {
            $final_order_id = intval($order_id);
        } else {
            // Try to extract numeric part if it's a string number
            $final_order_id = intval($order_id);
        }
    }
    
    // Priority 2: Extract from orderid string if it's like "ORD-123" or numeric
    if (empty($final_order_id) && !empty($orderid)) {
        if (is_numeric($orderid)) {
            // If orderid is already a numeric string, use it directly
            $final_order_id = intval($orderid);
        } elseif (preg_match('/ORD-?(\d+)/i', $orderid, $matches)) {
            // Extract numeric ID from "ORD-123" format
            $final_order_id = intval($matches[1]);
        } elseif (preg_match('/(\d+)/', $orderid, $matches)) {
            // Extract any numeric part from the string
            $final_order_id = intval($matches[1]);
        }
    }
    
    // Final validation
    if (empty($final_order_id) || $final_order_id <= 0) {
        throw new Exception('Order ID is required. Please provide a valid numeric ID or "ORD-{number}" format.');
    }
    
    // Query order - matching actual database structure (no shops table, no orderid column - only order_id)
    // Also join with halls, tables, and bills to get hall_name, table_number, and payment_status
    $sql = "SELECT 
                o.order_id,
                o.order_type,
                o.order_status,
                o.table_id,
                o.hall_id,
                o.customer_id,
                o.g_total_amount,
                o.discount_amount,
                o.service_charge,
                o.net_total_amount,
                o.payment_mode,
                o.order_taker_id,
                o.created_at,
                o.updated_at,
                o.terminal,
                o.branch_id,
                o.comments,
                COALESCE(h.name, '') as hall_name,
                COALESCE(t.table_number, '') as table_number,
                COALESCE(bill.payment_status, 'Unpaid') as payment_status,
                bill.bill_id
            FROM orders o
            LEFT JOIN halls h ON h.hall_id = o.hall_id
            LEFT JOIN tables t ON t.table_id = o.table_id AND t.branch_id = o.branch_id AND t.terminal = o.terminal
            LEFT JOIN bills bill ON bill.order_id = o.order_id
            WHERE o.order_id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    
    if (!$stmt) {
        throw new Exception('Error preparing statement: ' . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $final_order_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Error executing statement: ' . mysqli_error($connection));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception('Error getting result: ' . mysqli_error($connection));
    }
    
    $orderArray = array();
    if(mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $orderArray[] = $row;
        }
        // Return the first order if found (should be single order)
        $orderData = !empty($orderArray) ? $orderArray[0] : null;
        
        // Validate that we have actual order data
        if (!$orderData || empty($orderData['order_id'])) {
            throw new Exception('Order data is invalid or empty');
        }
        
        // Fetch order items/details
        $orderDetails = [];
        
        // Check if order_items table exists
        $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
        $has_order_items_table = ($check_table_items && mysqli_num_rows($check_table_items) > 0);
        
        if ($has_order_items_table) {
            // Check if is_cancel column exists in order_items table
            $check_cancel_col = mysqli_query($connection, "SHOW COLUMNS FROM order_items LIKE 'is_cancel'");
            $has_is_cancel_col = ($check_cancel_col && mysqli_num_rows($check_cancel_col) > 0);
            
            // Use order_items table - exclude cancelled items only if column exists
            $cancel_condition = $has_is_cancel_col ? "AND (oi.is_cancel IS NULL OR oi.is_cancel = 0)" : "";
            
            $items_sql = "SELECT 
                            oi.*,
                            COALESCE(d.name, 'Unknown Dish') as name,
                            d.dish_id
                         FROM order_items oi
                         LEFT JOIN dishes d ON oi.dish_id = d.dish_id
                         WHERE oi.order_id = ? $cancel_condition
                         ORDER BY oi.item_id ASC";
            
            $items_stmt = mysqli_prepare($connection, $items_sql);
            if ($items_stmt) {
                mysqli_stmt_bind_param($items_stmt, "i", $final_order_id);
                if (mysqli_stmt_execute($items_stmt)) {
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($row = mysqli_fetch_assoc($items_result)) {
                        $orderDetails[] = $row;
                    }
                }
                mysqli_stmt_close($items_stmt);
            }
        } else {
            // Check if is_cancel column exists in orderdetails table
            $check_cancel_col_od = mysqli_query($connection, "SHOW COLUMNS FROM orderdetails LIKE 'is_cancel'");
            $has_is_cancel_col_od = ($check_cancel_col_od && mysqli_num_rows($check_cancel_col_od) > 0);
            
            // Fallback to orderdetails table - exclude cancelled items only if column exists
            $orderid_str = 'ORD-' . $final_order_id;
            $cancel_condition_od = $has_is_cancel_col_od ? "AND (od.is_cancel IS NULL OR od.is_cancel = 0)" : "";
            
            $items_sql = "SELECT 
                            od.*,
                            COALESCE(d.name, 'Unknown Dish') as name,
                            d.dish_id
                         FROM orderdetails od
                         LEFT JOIN dishes d ON od.p_id = d.dish_id
                         WHERE od.orderid = ? $cancel_condition_od
                         ORDER BY od.id ASC";
            
            $items_stmt = mysqli_prepare($connection, $items_sql);
            if ($items_stmt) {
                mysqli_stmt_bind_param($items_stmt, "s", $orderid_str);
                if (mysqli_stmt_execute($items_stmt)) {
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    while ($row = mysqli_fetch_assoc($items_result)) {
                        $orderDetails[] = $row;
                    }
                }
                mysqli_stmt_close($items_stmt);
            }
            
            // Also try without ORD- prefix if still no items
            if (empty($orderDetails)) {
                $cancel_condition_od2 = $has_is_cancel_col_od ? "AND (od.is_cancel IS NULL OR od.is_cancel = 0)" : "";
                
                $items_sql = "SELECT 
                                od.*,
                                COALESCE(d.name, 'Unknown Dish') as name,
                                d.dish_id
                             FROM orderdetails od
                             LEFT JOIN dishes d ON od.p_id = d.dish_id
                             WHERE od.orderid = ? $cancel_condition_od2
                             ORDER BY od.id ASC";
                
                $items_stmt = mysqli_prepare($connection, $items_sql);
                if ($items_stmt) {
                    mysqli_stmt_bind_param($items_stmt, "s", $final_order_id);
                    if (mysqli_stmt_execute($items_stmt)) {
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        while ($row = mysqli_fetch_assoc($items_result)) {
                            $orderDetails[] = $row;
                        }
                    }
                    mysqli_stmt_close($items_stmt);
                }
            }
        }
        
        // Ensure items array is always present (even if empty)
        if (!isset($orderDetails)) {
            $orderDetails = [];
        }
        
        // Format the response to ensure all expected fields are present
        $formattedOrder = [
            'order_id' => intval($orderData['order_id'] ?? 0),
            'id' => intval($orderData['order_id'] ?? 0),
            'orderid' => 'ORD-' . $orderData['order_id'],
            'order_number' => 'ORD-' . $orderData['order_id'],
            'order_type' => $orderData['order_type'] ?? 'Dine In',
            'order_status' => $orderData['order_status'] ?? 'Pending',
            'status' => strtolower($orderData['order_status'] ?? 'pending'),
            'table_id' => $orderData['table_id'] ? intval($orderData['table_id']) : null,
            'table_number' => $orderData['table_number'] ?? null,
            'table_no' => $orderData['table_number'] ?? null, // Alias for backward compatibility
            'hall_id' => $orderData['hall_id'] ? intval($orderData['hall_id']) : null,
            'hall_name' => $orderData['hall_name'] ?? null,
            'customer_id' => $orderData['customer_id'] ? intval($orderData['customer_id']) : null,
            'g_total_amount' => floatval($orderData['g_total_amount'] ?? 0),
            'total' => floatval($orderData['g_total_amount'] ?? 0),
            'subtotal' => floatval($orderData['g_total_amount'] ?? 0),
            'net_total_amount' => floatval($orderData['net_total_amount'] ?? 0),
            'netTotal' => floatval($orderData['net_total_amount'] ?? 0),
            'discount_amount' => floatval($orderData['discount_amount'] ?? 0),
            'discount' => floatval($orderData['discount_amount'] ?? 0),
            'service_charge' => floatval($orderData['service_charge'] ?? 0),
            'payment_mode' => $orderData['payment_mode'] ?? 'Cash',
            'payment_status' => $orderData['payment_status'] ?? 'Unpaid',
            'is_paid' => ($orderData['payment_status'] ?? 'Unpaid') === 'Paid',
            'bill_id' => $orderData['bill_id'] ? intval($orderData['bill_id']) : null,
            'order_taker_id' => $orderData['order_taker_id'] ? intval($orderData['order_taker_id']) : null,
            'created_at' => $orderData['created_at'] ?? null,
            'updated_at' => $orderData['updated_at'] ?? null,
            'date' => $orderData['created_at'] ?? null,
            'terminal' => intval($orderData['terminal'] ?? 1),
            'branch_id' => $orderData['branch_id'] ? intval($orderData['branch_id']) : null,
            'comments' => $orderData['comments'] ?? null,
            'order_details' => $orderDetails, // Include order items
            'items' => $orderDetails // Alias for backward compatibility
        ];
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        // Support both response formats for backward compatibility
        $response_format = $input['format'] ?? ($_POST['format'] ?? 'single');
        
        if ($response_format === 'nested') {
            // Format like pos/getorderbyid.php (nested structure)
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Order fetched successfully.',
                'order' => $formattedOrder
            ]);
        } else {
            // Format like api/get_ordersbyid.php (flat structure)
            // Add success field for consistency
            $formattedOrder['success'] = true;
            echo json_encode($formattedOrder);
        }
        exit();
    } else {
        // If no record is found, return error
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'status' => 'error', 
            'message' => 'No order found for the given Order ID',
            'order_id' => $final_order_id ?? null
        ]);
        exit();
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    // Log error
    error_log("Get Order By ID Error: " . $e->getMessage());
    
    // Clear buffer and return error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    exit();
} catch (Error $e) {
    // Log fatal error
    error_log("Get Order By ID Fatal Error: " . $e->getMessage());
    
    // Clear buffer and return error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Fatal error: ' . $e->getMessage()]);
    exit();
}

// Don't close connection here - let PHP handle it automatically
exit();
?>
