<?php
/**
 * Direct USB Bill Receipt Printing API
 * 
 * PURPOSE: Prints bill receipts directly to USB-connected printer without user interaction
 * - Automatically finds and uses the default USB receipt printer
 * - No printer selection dialog needed
 * - Supports COM port and Windows printer name methods
 * 
 * Request Method: POST (Recommended) or GET
 * 
 * POST Parameters (JSON body):
 * - order_id (int/string, required) - Order ID (numeric or "ORD-123" format)
 * - branch_id (int, optional) - Branch ID to find branch-specific printer
 * - terminal (int, optional) - Terminal number (default: 1)
 * 
 * Example POST Request:
 *   POST /api/print_bill_direct.php
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
    
    // Get input from all possible sources
    $input = [];
    $raw_input = file_get_contents('php://input');
    
    // 1. PRIMARY: Try JSON body first (for POST/PUT requests with JSON)
    if ($raw_input) {
        $json_input = json_decode($raw_input, true);
        if ($json_input && is_array($json_input)) {
            $input = $json_input;
        }
    }
    
    // 2. Fallback: Merge POST form data
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    // 3. Fallback: Merge GET parameters
    if (empty($input) && !empty($_GET)) {
        $input = $_GET;
    } elseif (!empty($_GET)) {
        $input = array_merge($_GET, $input);
    }
    
    // Extract order_id from multiple possible formats
    $order_id = 0;
    $final_order_id = null;
    
    // Priority 1: Check order_id parameter
    if (isset($input['order_id']) && !empty($input['order_id'])) {
        $order_id_param = $input['order_id'];
        if (is_numeric($order_id_param)) {
            $final_order_id = intval($order_id_param);
        } elseif (is_string($order_id_param) && preg_match('/ORD-?(\d+)/i', $order_id_param, $matches)) {
            $final_order_id = intval($matches[1]);
        } elseif (is_string($order_id_param) && preg_match('/(\d+)/', $order_id_param, $matches)) {
            $final_order_id = intval($matches[1]);
        }
    }
    
    // Priority 2: Check orderid parameter
    if (empty($final_order_id) && isset($input['orderid']) && !empty($input['orderid'])) {
        $orderid_param = $input['orderid'];
        if (is_numeric($orderid_param)) {
            $final_order_id = intval($orderid_param);
        } elseif (is_string($orderid_param) && preg_match('/ORD-?(\d+)/i', $orderid_param, $matches)) {
            $final_order_id = intval($matches[1]);
        } elseif (is_string($orderid_param) && preg_match('/(\d+)/', $orderid_param, $matches)) {
            $final_order_id = intval($matches[1]);
        }
    }
    
    // Priority 3: Check id parameter
    if (empty($final_order_id) && isset($input['id']) && !empty($input['id'])) {
        $id_param = $input['id'];
        if (is_numeric($id_param)) {
            $final_order_id = intval($id_param);
        } elseif (is_string($id_param) && preg_match('/(\d+)/', $id_param, $matches)) {
            $final_order_id = intval($matches[1]);
        }
    }
    
    // Set the final order_id
    if (!empty($final_order_id) && $final_order_id > 0) {
        $order_id = $final_order_id;
    }
    
    $branch_id = isset($input['branch_id']) ? (empty($input['branch_id']) ? null : intval($input['branch_id'])) : null;
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    
    // Validate order_id
    if (empty($order_id) || $order_id <= 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        
        // Build helpful error message with debug info
        $received_params = [];
        if (isset($input['order_id'])) $received_params['order_id'] = $input['order_id'];
        if (isset($input['orderid'])) $received_params['orderid'] = $input['orderid'];
        if (isset($input['id'])) $received_params['id'] = $input['id'];
        
        $error_response = [
            'success' => false,
            'message' => 'Invalid order ID. The order_id parameter is required and must be a valid number.',
            'hint' => 'Send order_id in JSON body: {"order_id": 123}',
            'examples' => [
                'POST JSON' => '{"order_id": 123}',
                'GET Query' => '?order_id=123',
                'POST Form' => 'order_id=123'
            ]
        ];
        
        // Add debug info if available
        if (!empty($received_params)) {
            $error_response['received_parameters'] = $received_params;
            $error_response['note'] = 'Found parameters but could not parse valid order_id. Make sure order_id is a number (e.g., 123) or in format "ORD-123".';
        } else {
            $error_response['note'] = 'No order_id, orderid, or id parameter found in request.';
            $error_response['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        }
        
        echo json_encode($error_response, JSON_PRETTY_PRINT);
        exit();
    }
    
    // ============================================
    // STEP 1: Find USB Receipt Printer
    // ============================================
    $printer_config = null;
    
    // Build query to find USB receipt printer
    $printer_sql = "SELECT printer_id, name, connection_type, usb_port, printer_name, type, terminal, branch_id 
                    FROM printers 
                    WHERE type = 'receipt' 
                    AND connection_type = 'usb' 
                    AND status = 'active'";
    
    $printer_params = [];
    $printer_types = '';
    
    // Filter by branch_id if provided
    if ($branch_id !== null && $branch_id > 0) {
        $printer_sql .= " AND (branch_id = ? OR branch_id IS NULL)";
        $printer_params[] = $branch_id;
        $printer_types .= 'i';
    }
    
    // Filter by terminal
    $printer_sql .= " AND terminal = ?";
    $printer_params[] = $terminal;
    $printer_types .= 'i';
    
    // Order by branch_id (prefer branch-specific printer)
    $printer_sql .= " ORDER BY branch_id DESC, printer_id DESC LIMIT 1";
    
    $printer_stmt = mysqli_prepare($connection, $printer_sql);
    if ($printer_stmt) {
        if (!empty($printer_params)) {
            mysqli_stmt_bind_param($printer_stmt, $printer_types, ...$printer_params);
        }
        if (mysqli_stmt_execute($printer_stmt)) {
            $printer_result = mysqli_stmt_get_result($printer_stmt);
            $printer_config = mysqli_fetch_assoc($printer_result);
        }
        mysqli_stmt_close($printer_stmt);
    }
    
    // If no printer found, try without branch filter
    if (!$printer_config) {
        $printer_sql2 = "SELECT printer_id, name, connection_type, usb_port, printer_name, type, terminal, branch_id 
                        FROM printers 
                        WHERE type = 'receipt' 
                        AND connection_type = 'usb' 
                        AND status = 'active'
                        AND terminal = ?
                        ORDER BY printer_id DESC LIMIT 1";
        $printer_stmt2 = mysqli_prepare($connection, $printer_sql2);
        if ($printer_stmt2) {
            mysqli_stmt_bind_param($printer_stmt2, "i", $terminal);
            if (mysqli_stmt_execute($printer_stmt2)) {
                $printer_result2 = mysqli_stmt_get_result($printer_stmt2);
                $printer_config = mysqli_fetch_assoc($printer_result2);
            }
            mysqli_stmt_close($printer_stmt2);
        }
    }
    
    // Validate printer configuration
    if (!$printer_config) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No USB receipt printer configured. Please configure a USB printer in the printer management section.',
            'hint' => 'Add a printer with type="receipt" and connection_type="usb" in the printers table',
            'required_fields' => [
                'type' => 'receipt',
                'connection_type' => 'usb',
                'usb_port' => 'COM1, COM2, etc. (OR)',
                'printer_name' => 'Windows printer name'
            ]
        ]);
        exit();
    }
    
    // Check if printer has USB configuration
    $usb_port = $printer_config['usb_port'] ?? '';
    $printer_name = $printer_config['printer_name'] ?? '';
    
    if (empty($usb_port) && empty($printer_name)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'USB printer configuration incomplete. Please set either usb_port (COM port) or printer_name (Windows printer name).',
            'printer_id' => $printer_config['printer_id'],
            'printer_name' => $printer_config['name']
        ]);
        exit();
    }
    
    // ============================================
    // STEP 2: Fetch Order Details
    // ============================================
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
    
    // Get payment method
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
        $payment_method = ucfirst($payment_method_lower);
    }
    
    // Fetch order items
    $check_table_items = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
    $has_order_items_table = ($check_table_items && mysqli_num_rows($check_table_items) > 0);
    
    $items = [];
    
    if ($has_order_items_table) {
        $check_cancel_col = mysqli_query($connection, "SHOW COLUMNS FROM order_items LIKE 'is_cancel'");
        $has_is_cancel_col = ($check_cancel_col && mysqli_num_rows($check_cancel_col) > 0);
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
    
    // If no items found, try orderdetails table
    if (empty($items)) {
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
    
    // Try without ORD- prefix if still no items
    if (empty($items)) {
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
    
    // Validate that items exist
    if (empty($items)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot print receipt: No order items found. Please regenerate the bill or try again.',
            'order_id' => $order_id
        ]);
        exit();
    }
    
    // ============================================
    // STEP 3: Generate Receipt
    // ============================================
    $order_number = "ORD-{$order['order_id']}";
    $order_date = date('d/m/Y H:i:s', strtotime($order['created_at']));
    $order_type = $order['order_type'] ?: 'Dine In';
    if ($order_type === 'Take Away') {
        $order_type = 'Takeaway';
    }
    
    $branch_display = $branch_name ?: 'Restaurant Khas';
    $table_display = $order['table_number'] ?: $order_type;
    
    $subtotal = floatval($order['g_total_amount'] ?: 0);
    $service_charge = floatval($order['service_charge'] ?: 0);
    $discount = floatval($order['discount_amount'] ?: 0);
    $net_total = floatval($order['net_total_amount'] ?: $subtotal);
    
    // Build receipt using ESC/POS commands
    $receipt = chr(27) . chr(64); // Initialize printer
    
    // Logo/Brand Header
    $receipt .= chr(27) . chr(33) . chr(56); // Double height and width
    $receipt .= chr(27) . chr(69) . chr(1); // Bold
    $receipt .= chr(27) . chr(97) . chr(1); // Center alignment
    $receipt .= "RESTAURANT KHAS\n";
    $receipt .= chr(27) . chr(33) . chr(0); // Reset font size
    $receipt .= chr(27) . chr(69) . chr(0); // Disable bold
    
    // Branch name
    if ($branch_display && $branch_display !== 'Restaurant Khas') {
        $receipt .= chr(27) . chr(97) . chr(1); // Center
        $receipt .= $branch_display . "\n";
    }
    
    $receipt .= "--------------------------------\n";
    
    // Order Information
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
        $item_name = substr($item['dish_name'], 0, 28);
        $item_price = number_format(floatval($item['price']), 2);
        $item_qty = intval($item['quantity']);
        $item_total = number_format(floatval($item['total_amount'] ?: ($item['price'] * $item_qty)), 2);
        
        $receipt .= sprintf("%-30s\n", $item_name);
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
    
    // Payment method
    $receipt .= sprintf("%30s %8s\n", "Payment:", $payment_method);
    
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
    
    // Thank You Message
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
    
    // Cut paper
    $receipt .= chr(29) . chr(86) . chr(1); // Full cut
    
    // ============================================
    // STEP 4: Print to USB Printer
    // ============================================
    $print_success = false;
    $print_error = '';
    $printer_info = [];
    
    // Method 1: Try USB/COM port first (more direct)
    if (!empty($usb_port)) {
        $port_name = strtoupper(trim($usb_port));
        
        // Accept both COM port format (COM1, COM2, etc.) and USB port format (USB001, USB002, etc.)
        if (preg_match('/^(COM\d+|USB\d+)$/i', $port_name)) {
            // Try Method 1A: Direct port access (for COM ports)
            if (preg_match('/^COM\d+$/i', $port_name)) {
                $handle = @fopen($port_name, 'w');
                if ($handle) {
                    $bytes_written = @fwrite($handle, $receipt);
                    @fclose($handle);
                    
                    if ($bytes_written !== false && $bytes_written > 0) {
                        $print_success = true;
                        $printer_info = [
                            'connection_type' => 'usb',
                            'method' => 'com_port_direct',
                            'usb_port' => $port_name,
                            'bytes_written' => $bytes_written
                        ];
                    } else {
                        $print_error = "Failed to write to COM port: $port_name";
                    }
                }
            }
            
            // Try Method 1B: Windows print command with port name (for USB ports like USB002)
            if (!$print_success) {
                $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'receipt_' . time() . '_' . rand(1000, 9999) . '.txt';
                $file_written = @file_put_contents($temp_file, $receipt);
                
                if ($file_written !== false) {
                    // Try printing to the port using Windows print command
                    // Format: print /D:"USB002" "file.txt"
                    $print_cmd = 'print /D:"' . str_replace('"', '""', $port_name) . '" "' . $temp_file . '" 2>&1';
                    $output = [];
                    $return_var = 0;
                    exec($print_cmd, $output, $return_var);
                    
                    // Clean up temp file
                    @unlink($temp_file);
                    
                    if ($return_var === 0) {
                        $print_success = true;
                        $printer_info = [
                            'connection_type' => 'usb',
                            'method' => 'usb_port_via_print',
                            'usb_port' => $port_name,
                            'bytes_written' => $file_written
                        ];
                    } else {
                        $error_output = implode(' ', $output);
                        $print_error = "Could not print to port: $port_name. Error: $error_output";
                    }
                } else {
                    $print_error = "Could not create temporary file for printing";
                }
            }
            
            // Try Method 1C: Use Windows printer port (LPT or USB port as printer)
            if (!$print_success && preg_match('/^USB\d+$/i', $port_name)) {
                // Try to find printer using this port and use Windows printer name
                // This is a fallback method
                $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'receipt_' . time() . '_' . rand(1000, 9999) . '.txt';
                $file_written = @file_put_contents($temp_file, $receipt);
                
                if ($file_written !== false) {
                    // Try using copy command to port (alternative method)
                    $copy_cmd = 'copy /B "' . $temp_file . '" "' . $port_name . ':" 2>&1';
                    $output = [];
                    $return_var = 0;
                    exec($copy_cmd, $output, $return_var);
                    
                    @unlink($temp_file);
                    
                    if ($return_var === 0) {
                        $print_success = true;
                        $printer_info = [
                            'connection_type' => 'usb',
                            'method' => 'usb_port_via_copy',
                            'usb_port' => $port_name,
                            'bytes_written' => $file_written
                        ];
                    }
                }
            }
            
            // Final error message if all methods failed
            if (!$print_success && empty($print_error)) {
                $print_error = "Could not print to port: $port_name. Make sure the printer is connected, powered on, and the port is correct.";
            }
        } else {
            $print_error = "Invalid port format: $usb_port (expected COM1, COM2, USB001, USB002, etc.)";
        }
    }
    
    // Method 2: Try Windows printer name if COM port failed
    if (!$print_success && !empty($printer_name)) {
        $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'receipt_' . time() . '_' . rand(1000, 9999) . '.txt';
        $file_written = @file_put_contents($temp_file, $receipt);
        
        if ($file_written !== false) {
            // Use Windows print command
            $print_cmd = 'print /D:"' . str_replace('"', '""', $printer_name) . '" "' . $temp_file . '" 2>&1';
            $output = [];
            $return_var = 0;
            exec($print_cmd, $output, $return_var);
            
            // Clean up temp file
            @unlink($temp_file);
            
            if ($return_var === 0) {
                $print_success = true;
                $printer_info = [
                    'connection_type' => 'usb',
                    'method' => 'windows_printer_name',
                    'printer_name' => $printer_name,
                    'bytes_written' => $file_written
                ];
            } else {
                if (empty($print_error)) {
                    $print_error = "Failed to print to USB printer using Windows printer name: " . implode(' ', $output);
                }
            }
        } else {
            if (empty($print_error)) {
                $print_error = "Failed to create temporary file for printing";
            }
        }
    }
    
    // Clear buffer before output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Return response
    if ($print_success) {
        // Build safe printer_used array
        $printer_used_safe = [];
        if (isset($printer_config) && is_array($printer_config)) {
            $printer_used_safe = [
                'printer_id' => isset($printer_config['printer_id']) ? $printer_config['printer_id'] : null,
                'printer_name' => isset($printer_config['name']) ? $printer_config['name'] : 'USB Printer',
                'connection_type' => isset($printer_config['connection_type']) ? $printer_config['connection_type'] : 'usb'
            ];
        } else {
            $printer_used_safe = [
                'printer_id' => null,
                'printer_name' => 'USB Printer',
                'connection_type' => 'usb'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Receipt printed successfully to USB printer',
            'printer_info' => $printer_info,
            'order_number' => isset($order_number) ? $order_number : 'N/A',
            'printer_used' => $printer_used_safe
        ]);
    } else {
        http_response_code(500);
        $error_message = !empty($print_error) ? $print_error : 'Failed to print to USB printer';
        
        // Build safe printer config array
        $printer_config_safe = [];
        if (isset($printer_config) && is_array($printer_config)) {
            $printer_config_safe = [
                'printer_id' => isset($printer_config['printer_id']) ? $printer_config['printer_id'] : null,
                'printer_name' => isset($printer_config['name']) ? $printer_config['name'] : 'Unknown',
                'usb_port' => isset($usb_port) ? $usb_port : '',
                'windows_printer_name' => isset($printer_name) ? $printer_name : '',
                'connection_type' => isset($printer_config['connection_type']) ? $printer_config['connection_type'] : 'usb'
            ];
        } else {
            $printer_config_safe = [
                'printer_id' => null,
                'printer_name' => 'Not configured',
                'usb_port' => isset($usb_port) ? $usb_port : '',
                'windows_printer_name' => isset($printer_name) ? $printer_name : '',
                'connection_type' => 'usb'
            ];
        }
        
        // Get detailed debugging info
        $debug_info = [
            'usb_port_configured' => !empty($usb_port) ? $usb_port : 'Not set',
            'printer_name_configured' => !empty($printer_name) ? $printer_name : 'Not set',
            'port_format_valid' => !empty($usb_port) ? (preg_match('/^(COM\d+|USB\d+)$/i', strtoupper(trim($usb_port))) ? 'Yes' : 'No') : 'N/A',
            'temp_dir_writable' => is_writable(sys_get_temp_dir()) ? 'Yes' : 'No',
            'temp_dir' => sys_get_temp_dir()
        ];
        
        echo json_encode([
            'success' => false,
            'message' => $error_message,
            'printer_info' => $printer_info,
            'printer_config' => $printer_config_safe,
            'debug_info' => $debug_info,
            'troubleshooting' => [
                '1' => 'Check if printer is connected via USB and powered on',
                '2' => 'Verify USB port is correct (check Device Manager - look for "USB002" or similar)',
                '3' => 'Check if printer drivers are installed correctly',
                '4' => 'Try using Windows printer name instead of USB port (set printer_name in database)',
                '5' => 'Ensure printer is not set to "Offline" in Windows',
                '6' => 'Check printer queue for errors',
                '7' => 'Try unplugging and reconnecting USB cable',
                '8' => 'Verify printer has paper and is not in error state',
                '9' => 'Check Windows permissions - PHP needs access to printer ports'
            ],
            'suggested_fix' => !empty($printer_name) ? 
                'Try using Windows printer name method. Make sure printer_name in database matches exactly.' : 
                'Try adding printer_name to database (exact Windows printer name) as alternative to USB port.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Direct USB Print Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error printing receipt: ' . $e->getMessage()
    ]);
    exit();
    
} catch (Error $e) {
    error_log("Direct USB Print Fatal Error: " . $e->getMessage());
    
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

exit();
?>

