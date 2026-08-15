<?php

/**
 * Get Sales Report API
 * Fetches detailed sales report data for orders/bills
 * 
 * POST Parameters:
 * - branch_id: (optional) Filter by branch_id. If null or not provided, returns all branches (superadmin)
 * - start_date: (required) Start date in YYYY-MM-DD format
 * - end_date: (required) End date in YYYY-MM-DD format
 * - include_credit: (optional) Boolean to include credit sales. Default: true
 * 
 * Response:
 * - success: boolean
 * - data: array of sales records with all required fields
 */

// Include CORS headers FIRST - before any output or buffering
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

// Include database configuration
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

// Support both GET and POST requests
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET or POST.'
    ]);
    exit();
}

try {
    // Get input data
    if ($method === 'GET') {
        $data = $_GET;
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!$data || !is_array($data)) {
            $data = $_POST;
        }
    }
    
    // Get parameters
    $branchId = isset($data['branch_id']) ? $data['branch_id'] : null;
    $startDate = isset($data['start_date']) ? trim($data['start_date']) : null;
    $endDate = isset($data['end_date']) ? trim($data['end_date']) : null;
    $includeCredit = isset($data['include_credit']) ? filter_var($data['include_credit'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true;
    
    // Handle null, empty string, 'null', 'undefined', 'all' for branch_id
    if ($branchId === '' || $branchId === 'null' || $branchId === 'undefined' || $branchId === 'all') {
        $branchId = null;
    }
    
    // Validate that start_date and end_date are provided (REQUIRED)
    if (!$startDate || !$endDate) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'start_date and end_date are required parameters'
        ]);
        exit();
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid start_date format. Use YYYY-MM-DD format'
        ]);
        exit();
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid end_date format. Use YYYY-MM-DD format'
        ]);
        exit();
    }
    
    // Validate dates are valid
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);
    
    if ($startTimestamp === false || $endTimestamp === false) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date values. Please provide valid dates in YYYY-MM-DD format'
        ]);
        exit();
    }
    
    // Validate date range (start_date should not be after end_date)
    if ($startTimestamp > $endTimestamp) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'start_date cannot be after end_date'
        ]);
        exit();
    }
    
    // Build query to get orders with all related information
    // Based on actual database schema:
    // orders: order_id, branch_id, hall_id, table_id, order_taker_id, order_type, order_status, etc.
    // bills: bill_id, order_id, branch_id, total_amount, service_charge, discount, grand_total, payment_method, etc.
    // halls: hall_id, branch_id, name (not hall_name)
    // tables: table_id, branch_id, hall_id, table_number
    // users: id (not user_id), fullname, username
    $query = "
        SELECT 
            o.order_id,
            o.order_type,
            o.order_status,
            o.created_at,
            o.updated_at,
            o.branch_id,
            COALESCE(b.branch_name, CONCAT('Branch ', o.branch_id)) AS branch_name,
            h.name AS hall_name,
            t.table_number,
            u.fullname AS order_taker_name,
            u.username AS order_taker_username,
            bill.bill_id,
            bill.total_amount AS bill_amount,
            bill.service_charge,
            bill.discount AS discount_amount,
            bill.grand_total AS net_total,
            bill.payment_method,
            bill.payment_status,
            bill.created_at AS bill_created_at,
            bill.updated_at AS bill_updated_at,
            o.payment_mode AS order_payment_mode,
            o.customer_id,
            accountant_user.fullname AS accountant_name,
            accountant_user.username AS accountant_username,
            c.customer_id AS customer_table_id,
            c.name AS customer_name,
            c.phone AS customer_phone,
            c.email AS customer_email,
            c.balance AS customer_balance
        FROM orders o
        LEFT JOIN branches b ON o.branch_id = b.branch_id
        LEFT JOIN halls h ON o.hall_id = h.hall_id AND o.branch_id = h.branch_id
        LEFT JOIN tables t ON o.table_id = t.table_id AND o.branch_id = t.branch_id AND o.terminal = t.terminal
        LEFT JOIN users u ON o.order_taker_id = u.id
        INNER JOIN bills bill ON o.order_id = bill.order_id
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN (
            SELECT u1.branch_id, u1.fullname, u1.username
            FROM users u1
            WHERE u1.role = 'accountant'
            AND u1.id = (
                SELECT MIN(u2.id)
                FROM users u2
                WHERE u2.branch_id = u1.branch_id
                AND u2.role = 'accountant'
            )
        ) accountant_user ON accountant_user.branch_id = o.branch_id
        WHERE 1=1
        AND o.order_type != 'Customer Registration'
        AND o.order_status != 'Customer Created'
    ";
    
    $params = [];
    $types = '';
    
    // Add date filter - ALWAYS required (inclusive)
    $query .= " AND DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= 'ss';
    
    // Filter by branch_id if provided and not null
    if ($branchId !== null && $branchId !== '') {
        $branchId = intval($branchId);
        if ($branchId > 0) {
            $query .= " AND o.branch_id = ?";
            $params[] = $branchId;
            $types .= 'i';
        }
    }
    
    // Filter credit sales based on include_credit parameter
    // If include_credit is false, exclude credit sales
    // Credit sales are identified by payment_method containing 'credit' or payment_status = 'Unpaid' with customer_id
    if (!$includeCredit) {
        // Exclude credit sales
        // Credit sales have: payment_method LIKE '%credit%' OR (payment_status = 'Unpaid' AND customer_id IS NOT NULL)
        $query .= " AND NOT (
            (LOWER(bill.payment_method) LIKE '%credit%' OR LOWER(bill.payment_method) = 'cred')
            OR (LOWER(bill.payment_status) = 'unpaid' AND o.customer_id IS NOT NULL AND o.customer_id > 0)
        )";
    }
    
    // Only get completed orders (orders with bills)
    // Already handled by INNER JOIN bills
    
    $query .= " ORDER BY o.created_at DESC, o.order_id DESC";
    
    // Prepare and execute statement
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
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
    
    $sales = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Normalize payment method to show payment type: credit, cash, online, or bank
        // Check bills.payment_method first, then fallback to orders.payment_mode
        $payment_method_raw = $row['payment_method'] ?? $row['order_payment_mode'] ?? 'Cash';
        
        // Handle empty strings
        if (empty($payment_method_raw) || trim($payment_method_raw) === '') {
            $payment_method_raw = $row['order_payment_mode'] ?? 'Cash';
        }
        
        $payment_method = strtolower(trim($payment_method_raw));
        $payment_type = 'cash'; // default
        
        // Determine payment type based on payment method
        // Check for credit first (credit, cred)
        if ($payment_method === 'credit' || $payment_method === 'cred' || stripos($payment_method, 'credit') !== false) {
            $payment_type = 'credit';
        } elseif (stripos($payment_method, 'online') !== false || stripos($payment_method, 'upi') !== false || stripos($payment_method, 'digital') !== false || stripos($payment_method, 'card') !== false || stripos($payment_method, 'debit') !== false) {
            $payment_type = 'online';
        } elseif (stripos($payment_method, 'bank') !== false || stripos($payment_method, 'transfer') !== false || stripos($payment_method, 'cheque') !== false || stripos($payment_method, 'check') !== false) {
            $payment_type = 'bank';
        }
        // else: defaults to 'cash'
        
        // Determine if this is a credit sale
        // Credit sale if: payment_method is Credit OR (payment_status is Unpaid AND customer_id exists)
        $payment_status_lower = strtolower(trim($row['payment_status'] ?? ''));
        $customer_id = isset($row['customer_id']) && $row['customer_id'] > 0 ? intval($row['customer_id']) : null;
        $is_credit = false;
        
        if ($payment_type === 'credit') {
            $is_credit = true;
        } elseif ($payment_status_lower === 'unpaid' && $customer_id !== null && $customer_id > 0) {
            // Unpaid order with customer = credit sale
            $is_credit = true;
            $payment_type = 'credit'; // Override payment_type to credit
        }
        
        $sales[] = [
            'order_id' => intval($row['order_id']),
            'id' => intval($row['order_id']),
            'order_number' => 'ORD-' . $row['order_id'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
            'last_update' => $row['bill_updated_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? null,
            'order_type' => $row['order_type'] ?? 'N/A',
            'order_status' => $row['order_status'] ?? 'N/A',
            'branch_id' => intval($row['branch_id']),
            'branch_name' => $row['branch_name'] ?? '',
            'hall_name' => $row['hall_name'] ?? 'N/A',
            'hall' => $row['hall_name'] ?? 'N/A',
            'table_number' => $row['table_number'] ?? 'N/A',
            'table' => $row['table_number'] ?? 'N/A',
            'order_taker_name' => $row['order_taker_name'] ?? $row['order_taker_username'] ?? 'N/A',
            'order_taker' => $row['order_taker_name'] ?? $row['order_taker_username'] ?? 'N/A',
            'bill_id' => intval($row['bill_id'] ?? 0),
            'bill_amount' => floatval($row['bill_amount'] ?? 0),
            'g_total_amount' => floatval($row['bill_amount'] ?? 0),
            'total' => floatval($row['bill_amount'] ?? 0),
            'service_charge' => floatval($row['service_charge'] ?? 0),
            'discount_amount' => floatval($row['discount_amount'] ?? 0),
            'discount' => floatval($row['discount_amount'] ?? 0),
            'net_total' => floatval($row['net_total'] ?? 0),
            'net_total_amount' => floatval($row['net_total'] ?? 0),
            'grand_total' => floatval($row['net_total'] ?? 0),
            'payment_type' => $payment_type, // credit, cash, online, or bank
            'payment_mode' => $row['payment_method'] ?? 'Cash',
            'payment_method' => $is_credit ? 'Credit' : ($row['payment_method'] ?? 'Cash'),
            'payment_status' => $row['payment_status'] ?? 'N/A',
            'is_credit' => $is_credit,
            'customer_id' => $customer_id,
            // Customer information with multiple field name variations for frontend compatibility
            'customer_name' => $row['customer_name'] ?? null,
            'customerName' => $row['customer_name'] ?? null, // camelCase variant
            'customer_phone' => $row['customer_phone'] ?? null,
            'customerPhone' => $row['customer_phone'] ?? null, // camelCase variant
            'customer_email' => $row['customer_email'] ?? null,
            'customerEmail' => $row['customer_email'] ?? null, // camelCase variant
            'customer_balance' => isset($row['customer_balance']) ? floatval($row['customer_balance']) : null,
            'customerBalance' => isset($row['customer_balance']) ? floatval($row['customer_balance']) : null, // camelCase variant
            // Customer object for easy access
            'customer' => $customer_id ? [
                'id' => $customer_id,
                'customer_id' => $customer_id,
                'name' => $row['customer_name'] ?? null,
                'customer_name' => $row['customer_name'] ?? null,
                'phone' => $row['customer_phone'] ?? null,
                'customer_phone' => $row['customer_phone'] ?? null,
                'email' => $row['customer_email'] ?? null,
                'customer_email' => $row['customer_email'] ?? null,
                'balance' => isset($row['customer_balance']) ? floatval($row['customer_balance']) : null,
                'customer_balance' => isset($row['customer_balance']) ? floatval($row['customer_balance']) : null
            ] : null,
            'bill_by_name' => $row['accountant_name'] ?? $row['order_taker_name'] ?? $row['order_taker_username'] ?? 'N/A',
            'bill_created_at' => $row['bill_created_at'] ?? null,
            'bill_updated_at' => $row['bill_updated_at'] ?? null
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Calculate summary totals including credit sales
    $total_sales = 0;
    $credit_sales = 0;
    $cash_sales = 0;
    $online_sales = 0;
    $bank_sales = 0;
    $credit_count = 0;
    
    foreach ($sales as $sale) {
        $amount = floatval($sale['net_total'] ?? $sale['grand_total'] ?? 0);
        $total_sales += $amount;
        
        $payment_type = strtolower($sale['payment_type'] ?? 'cash');
        if ($payment_type === 'credit') {
            $credit_sales += $amount;
            $credit_count++;
        } elseif ($payment_type === 'online') {
            $online_sales += $amount;
        } elseif ($payment_type === 'bank') {
            $bank_sales += $amount;
        } else {
            $cash_sales += $amount;
        }
    }
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Ensure data is always an array (not null)
    if (!is_array($sales)) {
        $sales = [];
    }
    
    $response = json_encode([
        'success' => true,
        'data' => $sales,
        'count' => count($sales),
        'summary' => [
            'total_sales' => round($total_sales, 2),
            'credit_sales' => round($credit_sales, 2),
            'credit_count' => $credit_count,
            'cash_sales' => round($cash_sales, 2),
            'online_sales' => round($online_sales, 2),
            'bank_sales' => round($bank_sales, 2)
        ],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'branch_id' => $branchId
    ], JSON_UNESCAPED_UNICODE);
    
    if ($response === false) {
        throw new Exception('JSON encoding failed: ' . json_last_error_msg());
    }
    
    echo $response;
    exit();
    
} catch (Exception $e) {
    error_log("Get Sales Report Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching sales report: ' . $e->getMessage()
    ]);
    exit();
} catch (Error $e) {
    error_log("Get Sales Report Fatal Error: " . $e->getMessage());
    
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
} catch (Throwable $e) {
    // Catch any other unexpected errors
    error_log("Get Sales Report Unknown Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unknown error: ' . $e->getMessage()
    ]);
    exit();
}

exit();
?>

