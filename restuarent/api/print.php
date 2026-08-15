<?php
/**
 * Print Customer Receipt (Bill) to Network/USB Thermal Printer
 * 
 * PURPOSE: Prints final customer receipts/bills (NOT kitchen tickets)
 * - Supports Network printers (IP-based) for bills
 * - Supports USB printers for bills
 * - Full receipt with totals, payment method, etc.
 * 
 * This is DIFFERENT from pos/print.php which prints KOT (Kitchen Order Tickets)
 * 
 * Request Method: POST (Recommended) or GET
 * 
 * POST Parameters (JSON body):
 * - order_id (int/string, required) - Order ID (numeric or "ORD-123" format)
 * - orderid (string, optional) - Alternative to order_id ("ORD-123" format)
 * - id (int, optional) - Alternative to order_id (numeric only)
 * - printer_id (int, optional) - Printer ID from printers table
 * - printer_ip (string, optional) - Network printer IP address
 * - printer_name (string, optional) - USB printer name
 * - usb_port (string, optional) - USB COM port (e.g., COM1)
 * - connection_type (string, optional) - 'network' or 'usb' (default: 'network')
 * 
 * Example POST Request:
 *   POST /api/print.php
 *   Content-Type: application/json
 *   Body: {"order_id": 123}
 * 
 * Uses ESC/POS commands for thermal printer formatting
 */

// Include CORS headers FIRST
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
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}

// Check database connection
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
    // Determine request method
    $request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Get input from all possible sources - optimized for POST with JSON
    $input = [];
    $raw_input = file_get_contents('php://input');
    
    // 1. PRIMARY: Try JSON body first (for POST/PUT requests with JSON)
    // This is the recommended method (Option 1)
    if ($raw_input) {
        $json_input = json_decode($raw_input, true);
        if ($json_input && is_array($json_input)) {
            $input = $json_input; // Use JSON input directly (highest priority)
        }
    }
    
    // 2. Fallback: Merge POST form data (for form submissions)
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    // 3. Fallback: Merge GET parameters (for GET requests)
    if (empty($input) && !empty($_GET)) {
        $input = $_GET;
    } elseif (!empty($_GET)) {
        // If we have JSON input, also check GET for additional params
        $input = array_merge($_GET, $input);
    }
    
    // Extract order_id from multiple possible formats - more robust approach
    $order_id = 0;
    $final_order_id = null;
    
    // Priority 1: Check order_id parameter
    $order_id_param = null;
    if (isset($input['order_id']) && !empty($input['order_id'])) {
        $order_id_param = $input['order_id'];
    }
    
    // Priority 2: Check orderid parameter
    $orderid_param = null;
    if (isset($input['orderid']) && !empty($input['orderid'])) {
        $orderid_param = $input['orderid'];
    }
    
    // Priority 3: Check id parameter
    $id_param = null;
    if (isset($input['id']) && !empty($input['id'])) {
        $id_param = $input['id'];
    }
    
    // Process order_id parameter (highest priority)
    if (!empty($order_id_param)) {
        if (is_numeric($order_id_param)) {
            $final_order_id = intval($order_id_param);
        } elseif (is_string($order_id_param) && preg_match('/ORD-?(\d+)/i', $order_id_param, $matches)) {
            // Extract from "ORD-123" format
            $final_order_id = intval($matches[1]);
        } elseif (is_string($order_id_param) && preg_match('/(\d+)/', $order_id_param, $matches)) {
            // Extract any numeric part
            $final_order_id = intval($matches[1]);
        }
    }
    
    // Process orderid parameter (second priority)
    if (empty($final_order_id) && !empty($orderid_param)) {
        if (is_numeric($orderid_param)) {
            $final_order_id = intval($orderid_param);
        } elseif (is_string($orderid_param) && preg_match('/ORD-?(\d+)/i', $orderid_param, $matches)) {
            // Extract from "ORD-123" format
            $final_order_id = intval($matches[1]);
        } elseif (is_string($orderid_param) && preg_match('/(\d+)/', $orderid_param, $matches)) {
            // Extract any numeric part
            $final_order_id = intval($matches[1]);
        }
    }
    
    // Process id parameter (lowest priority)
    if (empty($final_order_id) && !empty($id_param)) {
        if (is_numeric($id_param)) {
            $final_order_id = intval($id_param);
        } elseif (is_string($id_param) && preg_match('/(\d+)/', $id_param, $matches)) {
            // Extract any numeric part
            $final_order_id = intval($matches[1]);
        }
    }
    
    // Set the final order_id
    if (!empty($final_order_id) && $final_order_id > 0) {
        $order_id = $final_order_id;
    }
    
    $printer_id = isset($input['printer_id']) ? intval($input['printer_id']) : 0;
    $printer_ip = isset($input['printer_ip']) ? trim($input['printer_ip']) : '';
    $printer_name = isset($input['printer_name']) ? trim($input['printer_name']) : '';
    $usb_port = isset($input['usb_port']) ? trim($input['usb_port']) : '';
    $connection_type = isset($input['connection_type']) ? trim($input['connection_type']) : 'network';
    
    // Validate order_id - must be provided and valid
    if (empty($order_id) || $order_id <= 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        
        // Create helpful error message with all possible values
        $received_params = [];
        $all_params_found = [];
        
        if (isset($input['order_id'])) {
            $received_params['order_id'] = $input['order_id'];
            $all_params_found[] = 'order_id: ' . var_export($input['order_id'], true);
        }
        if (isset($input['orderid'])) {
            $received_params['orderid'] = $input['orderid'];
            $all_params_found[] = 'orderid: ' . var_export($input['orderid'], true);
        }
        if (isset($input['id'])) {
            $received_params['id'] = $input['id'];
            $all_params_found[] = 'id: ' . var_export($input['id'], true);
        }
        
        $error_message = 'Invalid order ID. The order_id parameter is required and must be a valid number.';
        
        $response = [
            'success' => false,
            'message' => $error_message,
            'received_parameters' => $received_params,
            'parsed_order_id' => $order_id,
            'request_method' => $request_method,
        ];
        
        // Add appropriate hint based on request method
        if ($request_method === 'GET') {
            $response['hint'] = 'For GET requests, add order_id to query string: ?order_id=123';
            $response['example_url'] = '/api/print.php?order_id=123';
            $response['query_string_received'] = $_SERVER['QUERY_STRING'] ?? 'none';
        } else {
            $response['hint'] = 'For POST requests, send order_id in JSON body (RECOMMENDED): {"order_id": 123}';
            $response['example_body'] = '{"order_id": 123}';
            $response['example_curl'] = 'curl -X POST http://localhost/restuarent/api/print.php -H "Content-Type: application/json" -d \'{"order_id": 123}\'';
        }
        
        // Add debugging information
        $response['debug'] = [
            'all_input_keys' => array_keys($input),
            'order_id_value_received' => $order_id_param ?? null,
            'orderid_value_received' => $orderid_param ?? null,
            'id_value_received' => $id_param ?? null,
            'final_parsed_value' => $final_order_id ?? null,
            'GET_params' => $_GET,
            'POST_params' => $_POST,
            'raw_input_preview' => substr($raw_input, 0, 200),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        // Add helpful message
        if (!empty($all_params_found)) {
            $response['note'] = 'Found parameters but could not parse valid order_id: ' . implode(', ', $all_params_found);
        } else {
            $response['note'] = 'No order_id, orderid, or id parameters found in request.';
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    // Fetch order details with items - matching actual database structure
    // Also get payment_method from bills table (more accurate than orders.payment_mode)
    $sql = "
        SELECT 
            o.order_id,
            o.order_type,
            o.order_status,
            o.payment_mode AS order_payment_mode,
            o.g_total_amount,
            o.service_charge,
            o.discount_amount,
            o.net_total_amount,
            o.created_at,
            o.table_id,
            o.branch_id,
            o.terminal,
            o.order_taker_id,
            b.branch_name,
            t.table_number,
            u.fullname AS order_taker_name,
            bill.payment_method,
            bill.payment_status,
            bill.bill_id
        FROM orders o
        LEFT JOIN branches b ON o.branch_id = b.branch_id
        LEFT JOIN tables t ON o.table_id = t.table_id AND o.branch_id = t.branch_id AND o.terminal = t.terminal
        LEFT JOIN users u ON o.order_taker_id = u.id
        LEFT JOIN bills bill ON o.order_id = bill.order_id
        WHERE o.order_id = ?
        ORDER BY bill.bill_id DESC
        LIMIT 1
    ";
    
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]);
        exit();
    }
    
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Normalize branch_name
    $branch_name = $order['branch_name'] ?? null;
    if (!$branch_name && $order['branch_id']) {
        $branch_name = 'Branch ' . $order['branch_id'];
    }
    
    // Get payment method from bills table (more accurate) or fallback to order
    $payment_method = !empty($order['payment_method']) ? $order['payment_method'] : (!empty($order['order_payment_mode']) ? $order['order_payment_mode'] : 'Cash');
    
    // Normalize payment method display
    $payment_method_lower = strtolower(trim($payment_method));
    if (empty($payment_method) || $payment_method_lower === 'cash') {
        $payment_method = 'Cash';
    } elseif (in_array($payment_method_lower, ['credit', 'cred'])) {
        $payment_method = 'Credit';
    } elseif (in_array($payment_method_lower, ['card', 'debit', 'credit card'])) {
        $payment_method = 'Card';
    } elseif (in_array($payment_method_lower, ['online', 'upi', 'digital', 'netbanking'])) {
        $payment_method = 'Online';
    } else {
        // Capitalize first letter
        $payment_method = ucfirst($payment_method_lower);
    }
    
    // Fetch order items - try both order_items and orderdetails tables
    // Check if columns exist before querying to avoid errors
    $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
    $has_order_items_table = ($check_table_items && mysqli_num_rows($check_table_items) > 0);
    
    $items = [];
    
    if ($has_order_items_table) {
        // Check if is_cancel column exists in order_items table
        $check_cancel_col = mysqli_query($connection, "SHOW COLUMNS FROM order_items LIKE 'is_cancel'");
        $has_is_cancel_col = ($check_cancel_col && mysqli_num_rows($check_cancel_col) > 0);
        
        // Use order_items table - exclude cancelled items only if column exists
        $cancel_condition = $has_is_cancel_col ? "AND (oi.is_cancel IS NULL OR oi.is_cancel = 0)" : "";
        
        $items_sql = "
            SELECT 
                oi.item_id,
                oi.quantity,
                oi.price,
                oi.total_amount,
                COALESCE(d.name, 'Unknown Dish') AS dish_name
            FROM order_items oi
            LEFT JOIN dishes d ON oi.dish_id = d.dish_id
            WHERE oi.order_id = ? $cancel_condition
            ORDER BY oi.item_id ASC
        ";
        
        $items_stmt = mysqli_prepare($connection, $items_sql);
        if ($items_stmt) {
            mysqli_stmt_bind_param($items_stmt, "i", $order_id);
            if (mysqli_stmt_execute($items_stmt)) {
            $items_result = mysqli_stmt_get_result($items_stmt);
            
            while ($row = mysqli_fetch_assoc($items_result)) {
                $items[] = $row;
                }
            }
            mysqli_stmt_close($items_stmt);
        }
    }
    
    // If no items found in order_items, try orderdetails table (legacy)
    if (empty($items)) {
        // Check if is_cancel column exists in orderdetails table
        $check_cancel_col_od = mysqli_query($connection, "SHOW COLUMNS FROM orderdetails LIKE 'is_cancel'");
        $has_is_cancel_col_od = ($check_cancel_col_od && mysqli_num_rows($check_cancel_col_od) > 0);
        
        $orderid_str = 'ORD-' . $order_id;
        $cancel_condition_od = $has_is_cancel_col_od ? "AND (od.is_cancel IS NULL OR od.is_cancel = 0)" : "";
        
        $items_sql = "
            SELECT 
                od.p_id AS dish_id,
                od.qnty AS quantity,
                od.rate AS price,
                od.total AS total_amount,
                COALESCE(d.name, 'Unknown Dish') AS dish_name
            FROM orderdetails od
            LEFT JOIN dishes d ON od.p_id = d.dish_id
            WHERE od.orderid = ? $cancel_condition_od
            ORDER BY od.id ASC
        ";
        
        $items_stmt = mysqli_prepare($connection, $items_sql);
        if ($items_stmt) {
            mysqli_stmt_bind_param($items_stmt, "s", $orderid_str);
            if (mysqli_stmt_execute($items_stmt)) {
                $items_result = mysqli_stmt_get_result($items_stmt);
                
                while ($row = mysqli_fetch_assoc($items_result)) {
                    $items[] = $row;
                }
            }
            mysqli_stmt_close($items_stmt);
        }
    }
    
    // Also try without ORD- prefix if still no items
    if (empty($items)) {
        // Check if is_cancel column exists in orderdetails table
        $check_cancel_col_od2 = mysqli_query($connection, "SHOW COLUMNS FROM orderdetails LIKE 'is_cancel'");
        $has_is_cancel_col_od2 = ($check_cancel_col_od2 && mysqli_num_rows($check_cancel_col_od2) > 0);
        
        $cancel_condition_od2 = $has_is_cancel_col_od2 ? "AND (od.is_cancel IS NULL OR od.is_cancel = 0)" : "";
        
        $items_sql = "
            SELECT 
                od.p_id AS dish_id,
                od.qnty AS quantity,
                od.rate AS price,
                od.total AS total_amount,
                COALESCE(d.name, 'Unknown Dish') AS dish_name
            FROM orderdetails od
            LEFT JOIN dishes d ON od.p_id = d.dish_id
            WHERE od.orderid = ? $cancel_condition_od2
            ORDER BY od.id ASC
        ";
        
        $items_stmt = mysqli_prepare($connection, $items_sql);
        if ($items_stmt) {
            mysqli_stmt_bind_param($items_stmt, "s", $order_id);
            if (mysqli_stmt_execute($items_stmt)) {
            $items_result = mysqli_stmt_get_result($items_stmt);
            
            while ($row = mysqli_fetch_assoc($items_result)) {
                $items[] = $row;
                }
            }
            mysqli_stmt_close($items_stmt);
        }
    }
    
    // Validate that items exist before generating receipt
    if (empty($items)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        
        // Try to get order details to see if order exists
        $check_order_sql = "SELECT order_id, order_status, created_at FROM orders WHERE order_id = ?";
        $check_order_stmt = mysqli_prepare($connection, $check_order_sql);
        $order_exists = false;
        if ($check_order_stmt) {
            mysqli_stmt_bind_param($check_order_stmt, "i", $order_id);
            if (mysqli_stmt_execute($check_order_stmt)) {
                $check_result = mysqli_stmt_get_result($check_order_stmt);
                $order_exists = mysqli_num_rows($check_result) > 0;
            }
            mysqli_stmt_close($check_order_stmt);
        }
        
        // Better error message
        $error_msg = 'Cannot print receipt: No order items found. Please regenerate the bill or try again.';
        if (!$order_exists) {
            $error_msg = 'Order not found. Please check the order ID and try again.';
        }
        
        echo json_encode([
            'success' => false,
            'message' => $error_msg,
            'order_id' => $order_id,
            'order_exists' => $order_exists,
            'items' => [],
            'items_count' => 0,
            'debug_info' => [
                'checked_order_items_table' => $has_order_items_table ?? false,
                'checked_orderdetails_table' => true,
                'orderid_str_used' => 'ORD-' . $order_id,
                'numeric_order_id' => $order_id
            ]
        ]);
        exit();
    }
    
    // Format order number (generate from order_id since orderid column doesn't exist)
    $order_number = "ORD-{$order['order_id']}";
    
    // Format date
    $order_date = date('d/m/Y H:i:s', strtotime($order['created_at']));
    
    // Format order type
    $order_type = $order['order_type'] ?: 'Dine In';
    if ($order_type === 'Take Away') {
        $order_type = 'Takeaway';
    }
    
    // Get branch name (with fallback)
    $branch_display = $branch_name ?: 'Restaurant Khas';
    
    // Get table number or order type
    $table_display = $order['table_number'] ?: $order_type;
    
    // Calculate totals
    $subtotal = floatval($order['g_total_amount'] ?: 0);
    $service_charge = floatval($order['service_charge'] ?: 0);
    $discount = floatval($order['discount_amount'] ?: 0);
    $net_total = floatval($order['net_total_amount'] ?: $subtotal);
    
    // Build receipt using ESC/POS commands
    $receipt = chr(27) . chr(64); // Initialize printer
    
    // Logo/Brand Header (Centered, Bold, Double Size)
    $receipt .= chr(27) . chr(33) . chr(56); // Double height and width
    $receipt .= chr(27) . chr(69) . chr(1); // Bold
    $receipt .= chr(27) . chr(97) . chr(1); // Center alignment
    $receipt .= "RESTAURANT KHAS\n";
    $receipt .= chr(27) . chr(33) . chr(0); // Reset font size
    $receipt .= chr(27) . chr(69) . chr(0); // Disable bold
    
    // Branch name (if available)
    if ($branch_display && $branch_display !== 'Restaurant Khas') {
        $receipt .= chr(27) . chr(97) . chr(1); // Center
        $receipt .= $branch_display . "\n";
    }
    
    $receipt .= "--------------------------------\n";
    
    // Order Information (Centered)
    $receipt .= chr(27) . chr(97) . chr(1); // Center alignment
    $receipt .= "Date: $order_date\n";
    $receipt .= "Order #: $order_number\n";
    $receipt .= "Type: $order_type\n";
    
    if ($table_display && $order_type === 'Dine In') {
        $receipt .= "Table: $table_display\n";
    }
    
    if ($order['order_taker_name']) {
        $receipt .= "Order Taker: {$order['order_taker_name']}\n";
    }
    
    $receipt .= "--------------------------------\n";
    
    // Reset to left alignment
    $receipt .= chr(27) . chr(97) . chr(0);
    
    // Items Table Header
    $receipt .= sprintf("%-30s %6s %6s %8s\n", "Item", "Price", "Qty", "Total");
    $receipt .= "--------------------------------\n";
    
    // Order Items
    foreach ($items as $item) {
        $item_name = substr($item['dish_name'], 0, 28); // Limit to 28 chars
        $item_price = number_format(floatval($item['price']), 2);
        $item_qty = intval($item['quantity']);
        $item_total = number_format(floatval($item['total_amount'] ?: ($item['price'] * $item_qty)), 2);
        
        // Item name (first line)
        $receipt .= sprintf("%-30s\n", $item_name);
        
        // Price, Qty, Total (second line, right aligned)
        $receipt .= sprintf("%30s %6s %6d %8s\n", "", $item_price, $item_qty, $item_total);
    }
    
    $receipt .= "--------------------------------\n";
    
    // Totals Section
    $receipt .= sprintf("%30s %8s\n", "Subtotal:", number_format($subtotal, 2));
    
    if ($service_charge > 0) {
        $receipt .= sprintf("%30s %8s\n", "Service Charge:", number_format($service_charge, 2));
    }
    
    if ($discount > 0) {
        $receipt .= sprintf("%30s %8s\n", "Discount:", "-" . number_format($discount, 2));
    }
    
    $receipt .= chr(27) . chr(69) . chr(1); // Bold for net total
    $receipt .= "--------------------------------\n";
    $receipt .= sprintf("%30s %8s\n", "NET TOTAL:", number_format($net_total, 2));
    $receipt .= chr(27) . chr(69) . chr(0); // Disable bold
    $receipt .= "--------------------------------\n";
    
    // Payment method (use from bills table)
    $receipt .= sprintf("%30s %8s\n", "Payment:", $payment_method);
    
    // Show status - Credit payments always show "Credit" status
    $payment_method_lower = strtolower(trim($payment_method));
    $order_status_lower = isset($order['order_status']) ? strtolower(trim($order['order_status'])) : '';
    
    if ($payment_method_lower === 'credit' || $order_status_lower === 'credit') {
        $receipt .= sprintf("%30s %8s\n", "Status:", "Credit");
    } elseif (isset($order['payment_status'])) {
        $payment_status_display = ucfirst(strtolower($order['payment_status']));
        if ($payment_status_display !== 'Paid') {
            $receipt .= sprintf("%30s %8s\n", "Status:", $payment_status_display);
        }
    }
    
    // Thank You Message (Centered, Bold)
    $receipt .= "\n";
    $receipt .= chr(27) . chr(97) . chr(1); // Center alignment
    $receipt .= chr(27) . chr(69) . chr(1); // Bold
    $receipt .= "THANK YOU\n";
    $receipt .= chr(27) . chr(69) . chr(0); // Disable bold
    $receipt .= "Visit us again!\n";
    
    // Reset alignment
    $receipt .= chr(27) . chr(97) . chr(0);
    
    // Add padding for cutting
    $receipt .= "\n\n\n\n\n";
    
    // Cut paper (ESC/POS command)
    $receipt .= chr(29) . chr(86) . chr(1); // Full cut
    
    // Clear buffer before output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Get printer configuration if printer_id is provided
    $printer_config = null;
    if ($printer_id > 0) {
        $printer_sql = "SELECT printer_id, name, ip_address, port, connection_type, usb_port, printer_name, type 
                        FROM printers WHERE printer_id = ? AND status = 'active'";
        $printer_stmt = mysqli_prepare($connection, $printer_sql);
        if ($printer_stmt) {
            mysqli_stmt_bind_param($printer_stmt, "i", $printer_id);
            if (mysqli_stmt_execute($printer_stmt)) {
                $printer_result = mysqli_stmt_get_result($printer_stmt);
                $printer_config = mysqli_fetch_assoc($printer_result);
            }
            mysqli_stmt_close($printer_stmt);
        }
    }
    
    // Use printer config if available
    if ($printer_config) {
        $connection_type = $printer_config['connection_type'] ?? 'network';
        $printer_ip = $printer_config['ip_address'] ?? $printer_ip;
        $printer_name = $printer_config['printer_name'] ?? $printer_name;
        $usb_port = $printer_config['usb_port'] ?? $usb_port;
    }
    
    // Send to printer based on connection type
    $print_success = false;
    $print_error = '';
    $printer_info = [];
    
    if ($connection_type === 'usb' && (!empty($printer_name) || !empty($usb_port))) {
        // USB Printer - Use Windows print command or COM port
        if (!empty($printer_name)) {
            // Method 1: Use Windows printer name
            $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'receipt_' . time() . '.txt';
            file_put_contents($temp_file, $receipt);
            
            // Use Windows print command
            $print_cmd = 'print /D:"' . str_replace('"', '""', $printer_name) . '" "' . $temp_file . '" 2>&1';
            $output = [];
            $return_var = 0;
            exec($print_cmd, $output, $return_var);
            
            // Clean up temp file
            @unlink($temp_file);
            
            if ($return_var === 0) {
                $print_success = true;
                $printer_info = ['connection_type' => 'usb', 'printer_name' => $printer_name];
            } else {
                $print_error = "Failed to print to USB printer: " . implode(' ', $output);
            }
        } elseif (!empty($usb_port)) {
            // Method 2: Use COM port (for direct USB connection)
            $com_port = strtoupper(trim($usb_port));
            if (preg_match('/^COM\d+$/i', $com_port)) {
                $handle = @fopen($com_port, 'w');
                if ($handle) {
                    $bytes_written = @fwrite($handle, $receipt);
                    @fclose($handle);
                    
                    if ($bytes_written !== false && $bytes_written > 0) {
                        $print_success = true;
                        $printer_info = ['connection_type' => 'usb', 'usb_port' => $com_port];
                    } else {
                        $print_error = "Failed to write to COM port: $com_port";
                    }
                } else {
                    $print_error = "Could not open COM port: $com_port";
                }
            } else {
                $print_error = "Invalid COM port format: $usb_port (expected COM1, COM2, etc.)";
            }
        }
    } elseif (!empty($printer_ip)) {
        // Network Printer - Use IP address
        $printer_port = 9100;
        if ($printer_config && !empty($printer_config['port'])) {
            $printer_port = intval($printer_config['port']);
        }
        
        $socket = @fsockopen($printer_ip, $printer_port, $errno, $errstr, 5);
        
        if ($socket) {
            stream_set_timeout($socket, 3);
            $bytes_written = @fwrite($socket, $receipt);
            @fclose($socket);
            
            if ($bytes_written !== false && $bytes_written > 0) {
                $print_success = true;
                $printer_info = ['connection_type' => 'network', 'printer_ip' => $printer_ip, 'printer_port' => $printer_port];
            } else {
                $print_error = "Failed to write to network printer";
            }
        } else {
            // Try alternative ports
            $alternative_ports = [9100, 515, 631, 9101, 9102];
            foreach ($alternative_ports as $alt_port) {
                if ($alt_port == $printer_port) continue;
                $socket = @fsockopen($printer_ip, $alt_port, $errno, $errstr, 2);
                if ($socket) {
                    stream_set_timeout($socket, 3);
                    $bytes_written = @fwrite($socket, $receipt);
                    @fclose($socket);
                    if ($bytes_written !== false && $bytes_written > 0) {
                        $print_success = true;
                        $printer_info = ['connection_type' => 'network', 'printer_ip' => $printer_ip, 'printer_port' => $alt_port];
                        break;
                    }
                }
            }
            
            if (!$print_success) {
                $print_error = "Could not connect to network printer $printer_ip:$printer_port - $errstr ($errno)";
            }
        }
    }
    
    // Return response
    if ($print_success) {
        echo json_encode([
            'success' => true,
            'message' => 'Receipt sent to printer successfully',
            'printer_info' => $printer_info,
            'order_number' => $order_number
        ]);
    } elseif (!empty($print_error)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $print_error,
            'printer_info' => $printer_info
        ]);
    } else {
        // Return receipt data for browser printing or other use
        echo json_encode([
            'success' => true,
            'message' => 'Receipt generated successfully',
            'receipt_data' => base64_encode($receipt),
            'order' => [
                'order_id' => $order['order_id'],
                'order_number' => $order_number,
                'order_type' => $order_type,
                'branch_name' => $branch_display,
                'table_number' => $order['table_number'],
                'created_at' => $order['created_at']
            ],
            'items' => $items,
            'items_count' => count($items),
            'totals' => [
                'subtotal' => $subtotal,
                'service_charge' => $service_charge,
                'discount' => $discount,
                'net_total' => $net_total
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Print Receipt Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error generating receipt: ' . $e->getMessage()
    ]);
    exit();
    
} catch (Error $e) {
    error_log("Print Receipt Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
    exit();
}

// Don't close connection here - let PHP handle it automatically
exit();
?>
