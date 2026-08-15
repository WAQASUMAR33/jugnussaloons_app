<?php
/**
 * Order Management API
 * Handles CRUD operations for orders
 * Supports both JSON and form data
 * Updated to match actual database structure (order_id, order_type, order_status)
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

// Check connection
if (!isset($connection) || !$connection || !mysqli_ping($connection)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

// Get input data - handle both JSON, form data, and GET parameters
$input = [];
$raw_input = file_get_contents('php://input');

// For GET requests, use query string
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = $_GET;
} else {
    // For POST/PUT/DELETE requests, try JSON body first
    if ($raw_input) {
        $json_input = json_decode($raw_input, true);
        if ($json_input && is_array($json_input)) {
            $input = $json_input;
        }
    }
    
    // Fallback to POST form data
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
}

// Handle both GET and POST requests for fetching orders
// POST requests for fetching should not have order_id with update fields (order_status, order_type) or action='update'
$is_fetch_request = ($_SERVER['REQUEST_METHOD'] === 'GET') || 
                    ($_SERVER['REQUEST_METHOD'] === 'POST' && 
                     !isset($input['action']) && 
                     (!isset($input['order_id']) || 
                      (isset($input['order_id']) && !isset($input['order_status']) && !isset($input['order_type']) && !isset($input['table_id']) && !isset($input['g_total_amount']) && !isset($input['total']))));

if ($is_fetch_request) {
    
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    $branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : null;
    $status = isset($input['status']) ? trim($input['status']) : null;
    $order_id_input = isset($input['order_id']) ? $input['order_id'] : (isset($input['searchOrderId']) ? $input['searchOrderId'] : null);
    
    // Get date filter parameters
    $date = isset($input['date']) ? trim($input['date']) : null;
    $from_date = isset($input['from_date']) ? trim($input['from_date']) : null;
    $to_date = isset($input['to_date']) ? trim($input['to_date']) : null;
    
    // Convert branch_id to integer or null
    if ($branch_id_input === '' || $branch_id_input === 'null' || $branch_id_input === 'undefined' || $branch_id_input === null) {
        $branch_id = null;
    } else {
        $branch_id = intval($branch_id_input);
        if ($branch_id <= 0) {
            $branch_id = null;
        }
    }
    
    // Convert order_id to integer or null
    $order_id = null;
    if (!empty($order_id_input)) {
        // Handle both numeric ID and "ORD-123" format
        if (is_numeric($order_id_input)) {
            $order_id = intval($order_id_input);
        } elseif (is_string($order_id_input) && strpos($order_id_input, 'ORD-') === 0) {
            // Extract numeric part from "ORD-123"
            $order_id = intval(str_replace('ORD-', '', $order_id_input));
        }
        if ($order_id <= 0) {
            $order_id = null;
        }
    }
    
    try {
        // Build SQL query based on branch_id and status
        // Join with bills table to get payment_status
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
                    o.terminal,
                    o.branch_id,
                    o.comments,
                    t.table_number,
                    h.name AS hall_name,
                    b.branch_name,
                    bill.payment_status,
                    bill.payment_method,
                    bill.bill_id
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.table_id AND o.branch_id = t.branch_id AND o.terminal = t.terminal
                LEFT JOIN halls h ON o.hall_id = h.hall_id
                LEFT JOIN branches b ON o.branch_id = b.branch_id
                LEFT JOIN bills bill ON o.order_id = bill.order_id
                WHERE 1=1
                AND o.order_type != 'Customer Registration'
                AND o.order_status != 'Customer Created'";
        
        $params = [];
        $types = "";
        
        // Add branch filter if provided
        if ($branch_id !== null) {
            $sql .= " AND o.branch_id = ?";
            $params[] = $branch_id;
            $types .= "i";
        }
        
        // Add terminal filter
        $sql .= " AND o.terminal = ?";
        $params[] = $terminal;
        $types .= "i";
        
        // Add status filter if provided
        // Map frontend status values to database status values
        if ($status && $status !== 'all' && !empty($status)) {
            // Map lowercase frontend status to database status
            $status_map = [
                'pending' => 'Pending',
                'preparing' => 'Preparing',
                'ready' => 'Ready',
                'confirmed' => 'Confirmed',
                'completed' => 'Complete',
                'delivered' => 'Delivered',
                'paid' => 'Paid'
            ];
            
            // Convert to database status format
            $db_status = $status_map[strtolower($status)] ?? ucfirst($status);
            
            // Also check payment_status for 'paid' status
            if (strtolower($status) === 'paid') {
                $sql .= " AND (o.order_status = ? OR bill.payment_status = 'Paid')";
            } else {
                $sql .= " AND o.order_status = ?";
            }
            $params[] = $db_status;
            $types .= "s";
        }
        
        // Add order_id filter if provided (for search functionality)
        if ($order_id !== null) {
            $sql .= " AND o.order_id = ?";
            $params[] = $order_id;
            $types .= "i";
        }
        
        // Add date filtering
        // Priority: If single 'date' parameter is provided, use it
        // Otherwise, use from_date and to_date if provided
        if ($date) {
            // Single date filter (daily or backward compatibility)
            // Validate date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $sql .= " AND DATE(o.created_at) = ?";
                $params[] = $date;
                $types .= "s";
            }
        } elseif ($from_date && $to_date) {
            // Date range filter (weekly or custom)
            // Validate date formats
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
                // Ensure from_date is not after to_date
                if (strtotime($from_date) <= strtotime($to_date)) {
                    $sql .= " AND DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?";
                    $params[] = $from_date;
                    $params[] = $to_date;
                    $types .= "ss";
                }
            }
        } elseif ($from_date) {
            // Only from_date provided (use as single date)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
                $sql .= " AND DATE(o.created_at) = ?";
                $params[] = $from_date;
                $types .= "s";
            }
        }
        // If no date parameters provided, return all orders (no date filter)
        
        // Add ORDER BY clause
        if ($branch_id !== null) {
            $sql .= " ORDER BY o.created_at DESC, o.order_id DESC";
        } else {
            $sql .= " ORDER BY o.branch_id ASC, o.created_at DESC, o.order_id DESC";
        }
        
        // Prepare and execute statement
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        // Bind parameters
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result) {
            throw new Exception("Error executing query: " . mysqli_error($connection));
        }
        
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Normalize branch_name
            $branch_name = $row['branch_name'] ?? null;
            if (!$branch_name && $row['branch_id']) {
                $branch_name = 'Branch ' . $row['branch_id'];
            }
            
            // Normalize order status
            $order_status = $row['order_status'] ?? 'Pending';
            
            // Map database status to lowercase for frontend compatibility
            $status_lower = strtolower($order_status);
            // Handle special cases
            if ($status_lower === 'complete') {
                $status_lower = 'completed';
            }
            
            // Generate order_number
            $order_number = $row['order_id'] ? 'ORD-' . $row['order_id'] : '';
            
            // Normalize hall_name
            $hall_name = $row['hall_name'] ?? null;
            
            // Get payment_status from bills table (defaults to 'Unpaid' if no bill exists)
            $payment_status = $row['payment_status'] ?? 'Unpaid';
            
            // Determine if order should be considered 'paid' based on payment_status
            $is_paid_status = ($payment_status === 'Paid' || strtolower($payment_status) === 'paid');
            
            // Format dates
            $created_at = $row['created_at'] ?? null;
            $order_date = $created_at ? date('Y-m-d', strtotime($created_at)) : null;
            
            $orders[] = [
                'order_id' => intval($row['order_id']),
                'id' => intval($row['order_id']),
                'orderid' => $order_number,
                'order_number' => $order_number,
                'order_type' => $row['order_type'] ?? 'Dine In',
                'order_status' => $order_status,
                'status' => $status_lower, // Use normalized lowercase status
                'table_id' => $row['table_id'] ? intval($row['table_id']) : null,
                'tableid' => $row['table_id'],
                'table_number' => $row['table_number'] ?? null,
                'hall_id' => $row['hall_id'] ?? null,
                'hall_name' => $hall_name,
                'customer_name' => null,
                'g_total_amount' => floatval($row['g_total_amount'] ?? 0),
                'total' => floatval($row['g_total_amount'] ?? 0),
                'subtotal' => floatval($row['g_total_amount'] ?? 0),
                'net_total_amount' => floatval($row['net_total_amount'] ?? 0),
                'netTotal' => floatval($row['net_total_amount'] ?? 0),
                'discount_amount' => floatval($row['discount_amount'] ?? 0),
                'discount' => floatval($row['discount_amount'] ?? 0),
                'service_charge' => floatval($row['service_charge'] ?? 0),
                'payment_mode' => $row['payment_mode'] ?? 'Cash',
                'payment_method' => $row['payment_method'] ?? $row['payment_mode'] ?? 'Cash',
                'payment_status' => $payment_status,
                'is_paid' => $is_paid_status,
                'is_credit' => (strtolower(trim($row['payment_method'] ?? '')) === 'credit' || strtolower(trim($row['payment_mode'] ?? '')) === 'credit'),
                'bill_id' => $row['bill_id'] ? intval($row['bill_id']) : null,
                'order_taker_id' => $row['order_taker_id'] ? intval($row['order_taker_id']) : null,
                'created_at' => $created_at,
                'date' => $created_at, // Full datetime
                'order_date' => $order_date, // Date only (YYYY-MM-DD)
                'terminal' => intval($row['terminal']),
                'branch_id' => $row['branch_id'] ? intval($row['branch_id']) : null,
                'branch_name' => $branch_name,
                'comments' => $row['comments'] ?? null
            ];
        }
        
        mysqli_stmt_close($stmt);
        
        // Clear buffer before output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            'success' => true,
            'data' => $orders,
            'count' => count($orders)
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("Order Management GET Error: " . $e->getMessage());
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch orders',
            'message' => $e->getMessage()
        ]);
        exit();
    } catch (Error $e) {
        error_log("Order Management GET Fatal Error: " . $e->getMessage());
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || (isset($input['action']) && $input['action'] === 'delete')) {
    $order_id = $input['order_id'] ?? ($input['id'] ?? '');
    
    // Try to get from query string as well
    if (empty($order_id)) {
        parse_str(file_get_contents('php://input'), $deleteData);
        $order_id = $deleteData['order_id'] ?? $deleteData['id'] ?? '';
    }

    if (empty($order_id)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Order ID is required for deletion."]);
        exit();
    }

    try {
        // Start transaction
        mysqli_begin_transaction($connection);
        
        // Check if order_items table exists
        $check_items_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
        $has_order_items = mysqli_num_rows($check_items_table) > 0;
        
        // Delete order items first (foreign key constraint)
        if ($has_order_items) {
            $sql_delete_items = "DELETE FROM order_items WHERE order_id = ?";
            $stmt_delete_items = mysqli_prepare($connection, $sql_delete_items);
            mysqli_stmt_bind_param($stmt_delete_items, "i", $order_id);
            mysqli_stmt_execute($stmt_delete_items);
            mysqli_stmt_close($stmt_delete_items);
        } else {
            // Fallback to orderdetails table - generate orderid from order_id
            $orderid_str = 'ORD-' . $order_id;
            $sql_delete_details = "DELETE FROM orderdetails WHERE orderid = ?";
            $stmt_delete_details = mysqli_prepare($connection, $sql_delete_details);
            if ($stmt_delete_details) {
                mysqli_stmt_bind_param($stmt_delete_details, "s", $orderid_str);
                mysqli_stmt_execute($stmt_delete_details);
                mysqli_stmt_close($stmt_delete_details);
            }
        }
        
        // Delete order
        $sql_delete = "DELETE FROM orders WHERE order_id = ?";
        $stmt_delete = mysqli_prepare($connection, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $order_id);
        
        if (!mysqli_stmt_execute($stmt_delete)) {
            throw new Exception("Error deleting order: " . mysqli_error($connection));
        }
        
        $affected_rows = mysqli_stmt_affected_rows($stmt_delete);
        mysqli_stmt_close($stmt_delete);
        
        // Commit transaction
        mysqli_commit($connection);
        
        // Clear buffer before output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        if ($affected_rows > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Order deleted successfully."
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "No order found with the provided ID."
            ]);
        }
        exit();
    } catch (Exception $e) {
        error_log("Order Management DELETE Error: " . $e->getMessage());
        
        // Rollback transaction
        mysqli_rollback($connection);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
        exit();
    } catch (Error $e) {
        error_log("Order Management DELETE Fatal Error: " . $e->getMessage());
        
        // Rollback transaction
        mysqli_rollback($connection);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Fatal error: " . $e->getMessage()
        ]);
        exit();
    }
}

// Handle POST request (Update order)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $order_id = $input['order_id'] ?? ($input['id'] ?? '');
    $order_type = $input['order_type'] ?? ($_POST['order_type'] ?? 'Dine In');
    $order_status = $input['order_status'] ?? ($_POST['order_status'] ?? 'Pending');
    $table_id = isset($input['table_id']) ? intval($input['table_id']) : 0;
    $hall_id = isset($input['hall_id']) ? intval($input['hall_id']) : 0;
    $discount_amount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : (isset($input['discount']) ? floatval($input['discount']) : 0);
    $service_charge = isset($input['service_charge']) ? floatval($input['service_charge']) : 0;
    $g_total_amount = isset($input['g_total_amount']) ? floatval($input['g_total_amount']) : (isset($input['total']) ? floatval($input['total']) : 0);
    $net_total_amount = isset($input['net_total_amount']) ? floatval($input['net_total_amount']) : (isset($input['netTotal']) ? floatval($input['netTotal']) : ($g_total_amount + $service_charge - $discount_amount));
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    $payment_status = isset($input['payment_status']) ? trim($input['payment_status']) : null;
    $payment_mode = isset($input['payment_mode']) ? trim($input['payment_mode']) : null;
    
    // Get order_items if provided (for updating order items)
    $order_items = isset($input['order_items']) ? $input['order_items'] : (isset($input['items']) ? $input['items'] : null);
    if (is_string($order_items)) {
        $order_items = json_decode($order_items, true);
    }
    
    // Validate required fields
    if (empty($order_id)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Order ID is required for update."]);
        exit();
    }
    
    // Get branch_id for data isolation (optional but recommended)
    $branch_id = isset($input['branch_id']) && $input['branch_id'] ? intval($input['branch_id']) : null;
    
    // If branch_id not provided, try to get it from existing order
    if ($branch_id === null) {
        $get_order_sql = "SELECT branch_id FROM orders WHERE order_id = ? LIMIT 1";
        $get_order_stmt = mysqli_prepare($connection, $get_order_sql);
        if ($get_order_stmt) {
            mysqli_stmt_bind_param($get_order_stmt, "i", $order_id);
            mysqli_stmt_execute($get_order_stmt);
            $order_result = mysqli_stmt_get_result($get_order_stmt);
            $existing_order = mysqli_fetch_assoc($order_result);
            mysqli_stmt_close($get_order_stmt);
            if ($existing_order && !empty($existing_order['branch_id'])) {
                $branch_id = intval($existing_order['branch_id']);
            }
        }
    }

    try {
        // Start transaction for order update
        mysqli_begin_transaction($connection);
        
        $current_date = date("Y-m-d H:i:s");
        
        // Track if order items were updated (for KOT printing)
        $items_updated = false;
        $kitchens_to_print = [];
        
        // Track if table transfer occurred
        $table_transferred = false;
        $old_table_id = null;
        
        // Handle order_items update if provided
        if (!empty($order_items) && is_array($order_items)) {
            // Check if order_items table exists
            $check_items_table = mysqli_query($connection, "SHOW TABLES LIKE 'order_items'");
            $has_order_items = mysqli_num_rows($check_items_table) > 0;
            
            if ($has_order_items) {
                // Delete existing order items
                $delete_items_sql = "DELETE FROM order_items WHERE order_id = ?";
                $delete_items_stmt = mysqli_prepare($connection, $delete_items_sql);
                if ($delete_items_stmt) {
                    mysqli_stmt_bind_param($delete_items_stmt, "i", $order_id);
                    mysqli_stmt_execute($delete_items_stmt);
                    mysqli_stmt_close($delete_items_stmt);
                }
                
                // Insert new order items and track kitchens
                foreach ($order_items as $item) {
                    $dish_id = isset($item['dish_id']) ? intval($item['dish_id']) : 0;
                    $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                    $price = isset($item['price']) ? floatval($item['price']) : 0.00;
                    $total_amount = isset($item['total_amount']) ? floatval($item['total_amount']) : ($price * $quantity);
                    $notes = isset($item['notes']) ? trim($item['notes']) : null;
                    
                    if ($dish_id > 0 && $quantity > 0) {
                        // Get dish category to find kitchen
                        $dish_sql = "SELECT category_id, branch_id FROM dishes WHERE dish_id = ? LIMIT 1";
                        $dish_stmt = mysqli_prepare($connection, $dish_sql);
                        if ($dish_stmt) {
                            mysqli_stmt_bind_param($dish_stmt, "i", $dish_id);
                            mysqli_stmt_execute($dish_stmt);
                            $dish_result = mysqli_stmt_get_result($dish_stmt);
                            $dish_data = mysqli_fetch_assoc($dish_result);
                            mysqli_stmt_close($dish_stmt);
                            
                            if ($dish_data) {
                                // Get category kitchen_id
                                $cat_sql = "SELECT kid as kitchen_id FROM categories WHERE category_id = ? LIMIT 1";
                                $cat_stmt = mysqli_prepare($connection, $cat_sql);
                                if ($cat_stmt) {
                                    mysqli_stmt_bind_param($cat_stmt, "i", $dish_data['category_id']);
                                    mysqli_stmt_execute($cat_stmt);
                                    $cat_result = mysqli_stmt_get_result($cat_stmt);
                                    $cat_data = mysqli_fetch_assoc($cat_result);
                                    mysqli_stmt_close($cat_stmt);
                                    
                                    if ($cat_data && !empty($cat_data['kitchen_id'])) {
                                        $kitchen_id = intval($cat_data['kitchen_id']);
                                        if (!in_array($kitchen_id, $kitchens_to_print)) {
                                            $kitchens_to_print[] = $kitchen_id;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Insert order item
                        $insert_item_sql = "INSERT INTO order_items (order_id, dish_id, quantity, price, total_amount, notes, branch_id, created_at, updated_at) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                        $insert_item_stmt = mysqli_prepare($connection, $insert_item_sql);
                        if ($insert_item_stmt) {
                            $item_branch_id = $branch_id ?? $dish_data['branch_id'] ?? null;
                            mysqli_stmt_bind_param($insert_item_stmt, "iiiddsi", $order_id, $dish_id, $quantity, $price, $total_amount, $notes, $item_branch_id);
                            mysqli_stmt_execute($insert_item_stmt);
                            mysqli_stmt_close($insert_item_stmt);
                        }
                    }
                }
                
                $items_updated = true;
            }
        }
        
        // If payment_status is provided, update it in bills table
        if ($payment_status && in_array($payment_status, ['Paid', 'Unpaid'])) {
            // Check if bills table exists
            $check_bills_table = mysqli_query($connection, "SHOW TABLES LIKE 'bills'");
            $has_bills_table = mysqli_num_rows($check_bills_table) > 0;
            
            if ($has_bills_table) {
                // Find existing bill for this order
                $find_bill_sql = "SELECT bill_id FROM bills WHERE order_id = ? ORDER BY bill_id DESC LIMIT 1";
                $find_bill_stmt = mysqli_prepare($connection, $find_bill_sql);
                
                if ($find_bill_stmt) {
                    mysqli_stmt_bind_param($find_bill_stmt, "i", $order_id);
                    mysqli_stmt_execute($find_bill_stmt);
                    $bill_result = mysqli_stmt_get_result($find_bill_stmt);
                    $existing_bill = mysqli_fetch_assoc($bill_result);
                    mysqli_stmt_close($find_bill_stmt);
                    
                    if ($existing_bill) {
                        // Update payment_status in bills table
                        $update_bill_sql = "UPDATE bills SET payment_status = ?, updated_at = NOW()";
                        $update_bill_params = [$payment_status];
                        $update_bill_types = "s";
                        
                        // Also update payment_method if provided
                        if ($payment_mode) {
                            $update_bill_sql .= ", payment_method = ?";
                            $update_bill_params[] = $payment_mode;
                            $update_bill_types .= "s";
                        }
                        
                        $update_bill_sql .= " WHERE bill_id = ?";
                        $update_bill_params[] = $existing_bill['bill_id'];
                        $update_bill_types .= "i";
                        
                        $update_bill_stmt = mysqli_prepare($connection, $update_bill_sql);
                        if ($update_bill_stmt) {
                            mysqli_stmt_bind_param($update_bill_stmt, $update_bill_types, ...$update_bill_params);
                            mysqli_stmt_execute($update_bill_stmt);
                            mysqli_stmt_close($update_bill_stmt);
                            
                            // If payment_status is "Paid", update order status to "Complete"
                            if ($payment_status === 'Paid') {
                                if ($payment_mode) {
                                    $update_order_status_sql = "UPDATE orders SET order_status = 'Complete', payment_mode = ?, updated_at = NOW() WHERE order_id = ?";
                                    $update_order_stmt = mysqli_prepare($connection, $update_order_status_sql);
                                    if ($update_order_stmt) {
                                        mysqli_stmt_bind_param($update_order_stmt, "si", $payment_mode, $order_id);
                                        mysqli_stmt_execute($update_order_stmt);
                                        mysqli_stmt_close($update_order_stmt);
                                    }
                                } else {
                                    $update_order_status_sql = "UPDATE orders SET order_status = 'Complete', updated_at = NOW() WHERE order_id = ?";
                                    $update_order_stmt = mysqli_prepare($connection, $update_order_status_sql);
                                    if ($update_order_stmt) {
                                        mysqli_stmt_bind_param($update_order_stmt, "i", $order_id);
                                        mysqli_stmt_execute($update_order_stmt);
                                        mysqli_stmt_close($update_order_stmt);
                                    }
                                }
                            }
                            
                            // If payment_status is "Unpaid" and payment_mode is "Credit", update table status to "Available"
                            if ($payment_status === 'Unpaid' && $payment_mode && strtolower(trim($payment_mode)) === 'credit') {
                                // Get order details to find table_id, branch_id, terminal, and order_type
                                $get_order_for_unpaid_credit_sql = "SELECT table_id, branch_id, terminal, order_type FROM orders WHERE order_id = ? LIMIT 1";
                                $get_order_for_unpaid_credit_stmt = mysqli_prepare($connection, $get_order_for_unpaid_credit_sql);
                                if ($get_order_for_unpaid_credit_stmt) {
                                    mysqli_stmt_bind_param($get_order_for_unpaid_credit_stmt, "i", $order_id);
                                    if (mysqli_stmt_execute($get_order_for_unpaid_credit_stmt)) {
                                        $unpaid_credit_result = mysqli_stmt_get_result($get_order_for_unpaid_credit_stmt);
                                        $unpaid_credit_data = mysqli_fetch_assoc($unpaid_credit_result);
                                        mysqli_stmt_close($get_order_for_unpaid_credit_stmt);
                                        
                                        // Update table status to "Available" if it's a Dine In order with a table
                                        if ($unpaid_credit_data && isset($unpaid_credit_data['order_type']) && 
                                            $unpaid_credit_data['order_type'] === 'Dine In' &&
                                            isset($unpaid_credit_data['table_id']) && $unpaid_credit_data['table_id'] > 0) {
                                            $unpaid_credit_table_id = intval($unpaid_credit_data['table_id']);
                                            $unpaid_credit_branch_id = isset($unpaid_credit_data['branch_id']) ? intval($unpaid_credit_data['branch_id']) : ($branch_id ?? null);
                                            $unpaid_credit_terminal = isset($unpaid_credit_data['terminal']) ? intval($unpaid_credit_data['terminal']) : ($terminal ?? 1);
                                            
                                            if ($unpaid_credit_branch_id !== null) {
                                                $update_table_unpaid_credit_sql = "UPDATE tables 
                                                                                  SET status = 'Available', updated_at = NOW()
                                                                                  WHERE table_id = ? AND branch_id = ? AND terminal = ?";
                                                $update_table_unpaid_credit_stmt = mysqli_prepare($connection, $update_table_unpaid_credit_sql);
                                                if ($update_table_unpaid_credit_stmt) {
                                                    mysqli_stmt_bind_param($update_table_unpaid_credit_stmt, "iii", $unpaid_credit_table_id, $unpaid_credit_branch_id, $unpaid_credit_terminal);
                                                    mysqli_stmt_execute($update_table_unpaid_credit_stmt);
                                                    mysqli_stmt_close($update_table_unpaid_credit_stmt);
                                                    error_log("Credit payment (Unpaid): Table $unpaid_credit_table_id set to Available for order $order_id");
                                                }
                                            }
                                        }
                                    } else {
                                        mysqli_stmt_close($get_order_for_unpaid_credit_stmt);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Update order - matching actual database structure
        // Only update order fields if payment_status was not the only update (payment_status updates bills table separately)
        // If payment_status was provided and updated, we may have already updated order_status to "Complete"
        $update_order_fields = [];
        $update_order_values = [];
        $update_order_types = "";
        
        // Build dynamic update query
        if (isset($input['order_type'])) {
            $update_order_fields[] = "order_type = ?";
            $update_order_values[] = $order_type;
            $update_order_types .= "s";
        }
        
        // Only update order_status if payment_status wasn't "Paid" (which already sets it to "Complete")
        // Validate order_status - allowed values: Pending, Preparing, Ready, Complete, Cancelled, Bill Generated, Running
        if (isset($input['order_status']) && !($payment_status === 'Paid')) {
            $valid_statuses = ['Pending', 'Preparing', 'Ready', 'Complete', 'Cancelled', 'Bill Generated', 'Running'];
            if (!in_array($order_status, $valid_statuses)) {
                throw new Exception("Invalid status: '" . $order_status . "'. Allowed values: " . implode(', ', $valid_statuses));
            }
            $update_order_fields[] = "order_status = ?";
            $update_order_values[] = $order_status;
            $update_order_types .= "s";
        }
        
        if (isset($input['table_id'])) {
            // Handle table transfer for Dine In orders
            // Get existing order details to check if table is being changed
            $get_existing_order_sql = "SELECT table_id, order_type, branch_id, terminal FROM orders WHERE order_id = ? LIMIT 1";
            $get_existing_order_stmt = mysqli_prepare($connection, $get_existing_order_sql);
            $existing_order_type = null;
            $existing_branch_id = null;
            $existing_terminal = null;
            
            if ($get_existing_order_stmt) {
                mysqli_stmt_bind_param($get_existing_order_stmt, "i", $order_id);
                if (mysqli_stmt_execute($get_existing_order_stmt)) {
                    $existing_order_result = mysqli_stmt_get_result($get_existing_order_stmt);
                    $existing_order_data = mysqli_fetch_assoc($existing_order_result);
                    if ($existing_order_data) {
                        $old_table_id = isset($existing_order_data['table_id']) && $existing_order_data['table_id'] > 0 ? intval($existing_order_data['table_id']) : null;
                        $existing_order_type = $existing_order_data['order_type'] ?? null;
                        $existing_branch_id = isset($existing_order_data['branch_id']) ? intval($existing_order_data['branch_id']) : null;
                        $existing_terminal = isset($existing_order_data['terminal']) ? intval($existing_order_data['terminal']) : 1;
                    }
                }
                mysqli_stmt_close($get_existing_order_stmt);
            }
            
            // Check if this is a table transfer (table_id is being changed and order is Dine In)
            // Use the order_type from input if provided, otherwise use existing order_type
            $current_order_type = isset($input['order_type']) ? $order_type : $existing_order_type;
            $is_table_transfer = false;
            if ($current_order_type === 'Dine In' && $old_table_id !== null && $old_table_id > 0 && 
                $table_id > 0 && $table_id != $old_table_id) {
                $is_table_transfer = true;
            }
            
            // If table transfer is happening, update table statuses
            if ($is_table_transfer) {
                // Use branch_id and terminal from existing order, or from input, or default
                $table_branch_id = $existing_branch_id ?? $branch_id ?? null;
                $table_terminal = $existing_terminal ?? $terminal ?? 1;
                
                // Update old table status to "Available"
                if ($table_branch_id !== null) {
                    $update_old_table_sql = "UPDATE tables 
                                            SET status = 'Available', updated_at = NOW()
                                            WHERE table_id = ? AND branch_id = ? AND terminal = ?";
                    $update_old_table_stmt = mysqli_prepare($connection, $update_old_table_sql);
                    if ($update_old_table_stmt) {
                        mysqli_stmt_bind_param($update_old_table_stmt, "iii", $old_table_id, $table_branch_id, $table_terminal);
                        mysqli_stmt_execute($update_old_table_stmt);
                        mysqli_stmt_close($update_old_table_stmt);
                        error_log("Table transfer: Old table $old_table_id set to Available");
                    }
                    
                    // Update new table status to "Running"
                    $update_new_table_sql = "UPDATE tables 
                                            SET status = 'Running', updated_at = NOW()
                                            WHERE table_id = ? AND branch_id = ? AND terminal = ?";
                    $update_new_table_stmt = mysqli_prepare($connection, $update_new_table_sql);
                    if ($update_new_table_stmt) {
                        mysqli_stmt_bind_param($update_new_table_stmt, "iii", $table_id, $table_branch_id, $table_terminal);
                        mysqli_stmt_execute($update_new_table_stmt);
                        mysqli_stmt_close($update_new_table_stmt);
                        error_log("Table transfer: New table $table_id set to Running");
                        $table_transferred = true;
                    }
                }
            }
            
            $update_order_fields[] = "table_id = ?";
            $update_order_values[] = $table_id;
            $update_order_types .= "i";
        }
        
        if (isset($input['hall_id'])) {
            $update_order_fields[] = "hall_id = ?";
            $update_order_values[] = $hall_id;
            $update_order_types .= "i";
        }
        
        if (isset($input['g_total_amount']) || isset($input['total'])) {
            $update_order_fields[] = "g_total_amount = ?";
            $update_order_values[] = $g_total_amount;
            $update_order_types .= "d";
        }
        
        if (isset($input['discount_amount']) || isset($input['discount'])) {
            $update_order_fields[] = "discount_amount = ?";
            $update_order_values[] = $discount_amount;
            $update_order_types .= "d";
        }
        
        if (isset($input['service_charge'])) {
            $update_order_fields[] = "service_charge = ?";
            $update_order_values[] = $service_charge;
            $update_order_types .= "d";
        }
        
        if (isset($input['net_total_amount']) || isset($input['netTotal'])) {
            $update_order_fields[] = "net_total_amount = ?";
            $update_order_values[] = $net_total_amount;
            $update_order_types .= "d";
        }
        
        if (isset($input['terminal'])) {
            $update_order_fields[] = "terminal = ?";
            $update_order_values[] = $terminal;
            $update_order_types .= "i";
        }
        
        if (isset($input['payment_mode']) && !$payment_status) {
            $update_order_fields[] = "payment_mode = ?";
            $update_order_values[] = $payment_mode;
            $update_order_types .= "s";
        }
        
        // Only update orders table if there are fields to update
        if (!empty($update_order_fields)) {
            $update_order_fields[] = "updated_at = NOW()";
            
            $sql = "UPDATE orders SET " . implode(", ", $update_order_fields) . " WHERE order_id = ?";
            
            // Add branch_id filter if provided
            if ($branch_id !== null) {
                $sql .= " AND branch_id = ?";
            }
            
            $update_order_values[] = $order_id;
            $update_order_types .= "i";
            
            if ($branch_id !== null) {
                $update_order_values[] = $branch_id;
                $update_order_types .= "i";
            }
            
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, $update_order_types, ...$update_order_values);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error updating order: " . mysqli_error($connection));
            }
            
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
        } else {
            // If only payment_status was updated, still return success
            $affected_rows = 1;
        }
        
        // If payment_mode is Credit, update table status to "Available" for Dine In orders
        if (isset($input['payment_mode']) && strtolower(trim($payment_mode)) === 'credit') {
            // Get order details to find table_id, branch_id, terminal, and order_type
            $get_order_for_credit_sql = "SELECT table_id, branch_id, terminal, order_type FROM orders WHERE order_id = ? LIMIT 1";
            $get_order_for_credit_stmt = mysqli_prepare($connection, $get_order_for_credit_sql);
            if ($get_order_for_credit_stmt) {
                mysqli_stmt_bind_param($get_order_for_credit_stmt, "i", $order_id);
                if (mysqli_stmt_execute($get_order_for_credit_stmt)) {
                    $order_for_credit_result = mysqli_stmt_get_result($get_order_for_credit_stmt);
                    $order_for_credit_data = mysqli_fetch_assoc($order_for_credit_result);
                    mysqli_stmt_close($get_order_for_credit_stmt);
                    
                    // Update table status to "Available" if it's a Dine In order with a table
                    if ($order_for_credit_data && isset($order_for_credit_data['order_type']) && 
                        $order_for_credit_data['order_type'] === 'Dine In' &&
                        isset($order_for_credit_data['table_id']) && $order_for_credit_data['table_id'] > 0) {
                        $credit_table_id = intval($order_for_credit_data['table_id']);
                        $credit_branch_id = isset($order_for_credit_data['branch_id']) ? intval($order_for_credit_data['branch_id']) : ($branch_id ?? null);
                        $credit_terminal = isset($order_for_credit_data['terminal']) ? intval($order_for_credit_data['terminal']) : ($terminal ?? 1);
                        
                        if ($credit_branch_id !== null) {
                            $update_table_for_credit_sql = "UPDATE tables 
                                                            SET status = 'Available', updated_at = NOW()
                                                            WHERE table_id = ? AND branch_id = ? AND terminal = ?";
                            $update_table_for_credit_stmt = mysqli_prepare($connection, $update_table_for_credit_sql);
                            if ($update_table_for_credit_stmt) {
                                mysqli_stmt_bind_param($update_table_for_credit_stmt, "iii", $credit_table_id, $credit_branch_id, $credit_terminal);
                                mysqli_stmt_execute($update_table_for_credit_stmt);
                                mysqli_stmt_close($update_table_for_credit_stmt);
                                error_log("Order payment mode set to Credit: Table $credit_table_id set to Available for order $order_id");
                            }
                        }
                    }
                } else {
                    mysqli_stmt_close($get_order_for_credit_stmt);
                }
            }
        }
        
        // Commit transaction
        mysqli_commit($connection);
        
        // Print KOT to kitchens if order items were updated
        if ($items_updated && !empty($kitchens_to_print)) {
            // Include the print function
            require_once __DIR__ . '/print_kitchen_function.php';
            
            $print_results = [];
            foreach ($kitchens_to_print as $kitchen_id) {
                // Call print function directly (no HTTP needed, avoids CORS issues)
                $print_response = print_kitchen_receipt_direct($connection, $order_id, $kitchen_id, $branch_id);
                
                $print_results[] = [
                    'kitchen_id' => $kitchen_id,
                    'success' => $print_response['success'] ?? false,
                    'message' => $print_response['message'] ?? 'Unknown error'
                ];
                
                if (isset($print_response['success']) && $print_response['success']) {
                    error_log("Edit Order KOT Print Success: KOT sent to kitchen $kitchen_id for order $order_id");
                } else {
                    $error_msg = $print_response['message'] ?? 'Unknown error';
                    error_log("Edit Order KOT Print Error: $error_msg for kitchen $kitchen_id, order $order_id");
                }
            }
        }
        
        if ($affected_rows > 0 || $payment_status || $items_updated) {
            $message = "Order updated successfully.";
            if ($payment_status) {
                $message = "Payment status updated successfully.";
            } elseif ($items_updated) {
                $message = "Order and items updated successfully.";
            }
            
            // Add table transfer info if applicable
            $response = [
                "success" => true,
                "message" => $message,
                "order_id" => intval($order_id)
            ];
            
            if (isset($table_transferred) && $table_transferred) {
                $response["table_transferred"] = true;
                $response["old_table_id"] = isset($old_table_id) ? $old_table_id : null;
                $response["new_table_id"] = $table_id;
                if ($message === "Order updated successfully.") {
                    $response["message"] = "Order transferred from Table $old_table_id to Table $table_id successfully.";
                }
            }
            
            if ($payment_status) {
                $response["payment_status"] = $payment_status;
            }
            
            if ($items_updated && !empty($kitchens_to_print)) {
                $response["kot_printed"] = true;
                $response["kitchens_printed"] = count($kitchens_to_print);
                $response["print_results"] = $print_results ?? [];
            }
            
            // Clear buffer before output
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            echo json_encode($response);
        } else {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "No order found with the provided ID or no changes were made."
            ]);
        }
        exit();
    } catch (Exception $e) {
        error_log("Order Management POST Error: " . $e->getMessage());
        
        // Rollback transaction on error
        mysqli_rollback($connection);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
        exit();
    } catch (Error $e) {
        error_log("Order Management POST Fatal Error: " . $e->getMessage());
        
        // Rollback transaction on error
        mysqli_rollback($connection);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Fatal error: " . $e->getMessage()
        ]);
        exit();
    }
}

// Invalid request method
while (ob_get_level() > 0) {
    ob_end_clean();
}
if (!headers_sent()) {
    header("Content-Type: application/json; charset=UTF-8");
}
http_response_code(405);
echo json_encode(["success" => false, "message" => "Invalid request method."]);
exit();
?>
