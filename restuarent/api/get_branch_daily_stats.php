<?php
/**
 * Get Branch Daily Statistics API
 * Returns consolidated branch statistics in a single call
 * Reduces the number of API requests needed by the frontend
 * 
 * GET/POST Parameters:
 * - branch_id: int (required) - Branch ID to get statistics for
 * - date: string (optional) - Date in YYYY-MM-DD format (defaults to today)
 * - from_date: string (optional) - Start date in YYYY-MM-DD format
 * - to_date: string (optional) - End date in YYYY-MM-DD format
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "data": {
 *     "branch_id": 1,
 *     "date": "2024-01-15",
 *     "sales": {
 *       "total": 2345.67,
 *       "net_total": 2345.67,
 *       "grand_total": 2345.67,
 *       "amount": 2345.67,
 *       "total_orders": 45,
 *       "cash_sales": 2000.00,
 *       "credit_sales": 345.67
 *     },
 *     "orders": {
 *       "total": 50,
 *       "pending": 5,
 *       "preparing": 10,
 *       "ready": 3,
 *       "confirmed": 2,
 *       "completed": 25,
 *       "delivered": 3,
 *       "paid": 2
 *     }
 *   }
 * }
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

// Ensure connection is alive
if (isset($connection) && $connection) {
    if (!mysqli_ping($connection)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["success" => false, "message" => "Database connection lost"]);
        exit();
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Handle OPTIONS request for CORS
    if ($method === 'OPTIONS') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        exit();
    }
    
    // Get input data - handle both JSON, POST, and GET
    $input = [];
    $raw_input = file_get_contents('php://input');
    
    // For POST requests, try JSON body first
    if ($method === 'POST' && $raw_input) {
        $json_input = json_decode($raw_input, true);
        if ($json_input && is_array($json_input)) {
            $input = $json_input;
        }
    }
    
    // Fallback to POST form data
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    // For GET requests, use query parameters
    if ($method === 'GET' && empty($input)) {
        if (!empty($_GET)) {
            $input = $_GET;
        }
    }
    
    // Get parameters
    $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
    $date = isset($input['date']) ? trim($input['date']) : date('Y-m-d');
    $from_date = isset($input['from_date']) ? trim($input['from_date']) : null;
    $to_date = isset($input['to_date']) ? trim($input['to_date']) : null;
    
    // Validate parameters
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
            'message' => 'Branch ID is required'
        ]);
        exit();
    }
    
    // Validate date format if provided
    if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date format. Use YYYY-MM-DD'
        ]);
        exit();
    }
    
    // Get last dayend closing_date_time for the branch to filter today's sales
    $last_dayend_date = null;
    try {
        $dayend_sql = "SELECT closing_date_time FROM dayend WHERE branch_id = ? ORDER BY closing_date_time DESC LIMIT 1";
        $dayend_stmt = mysqli_prepare($connection, $dayend_sql);
        if ($dayend_stmt) {
            mysqli_stmt_bind_param($dayend_stmt, "i", $branch_id);
            mysqli_stmt_execute($dayend_stmt);
            $dayend_result = mysqli_stmt_get_result($dayend_stmt);
            $dayend_row = mysqli_fetch_assoc($dayend_result);
            if ($dayend_row && !empty($dayend_row['closing_date_time'])) {
                $last_dayend_date = $dayend_row['closing_date_time'];
            }
            mysqli_stmt_close($dayend_stmt);
        }
    } catch (Exception $e) {
        error_log('Error getting last dayend: ' . $e->getMessage());
    }
    
    // Build date filter
    $dateFilter = '';
    $dateParams = [];
    $dateTypes = '';
    
    if ($from_date && $to_date) {
        // Date range provided
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid date format. Use YYYY-MM-DD'
            ]);
            exit();
        }
        $dateFilter = " AND DATE(o.created_at) BETWEEN ? AND ?";
        $dateParams[] = $from_date;
        $dateParams[] = $to_date;
        $dateTypes = 'ss';
    } else {
        // Single date - if no dayend, use the date; if dayend exists, use after dayend
        if ($last_dayend_date) {
            $dateFilter = " AND o.created_at > ?";
            $dateParams[] = $last_dayend_date;
            $dateTypes = 's';
        } else {
            $dateFilter = " AND DATE(o.created_at) = ?";
            $dateParams[] = $date;
            $dateTypes = 's';
        }
    }
    
    // Get sales statistics
    $sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) as total_sales,
            COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) as net_total,
            COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) as grand_total,
            COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) as amount,
            COALESCE(SUM(CASE WHEN LOWER(bill.payment_method) = 'credit' OR LOWER(bill.payment_method) = 'cred' THEN COALESCE(bill.grand_total, o.net_total_amount, 0) ELSE 0 END), 0) as credit_sales,
            COALESCE(SUM(CASE WHEN LOWER(bill.payment_method) != 'credit' AND LOWER(bill.payment_method) != 'cred' OR bill.payment_method IS NULL THEN COALESCE(bill.grand_total, o.net_total_amount, 0) ELSE 0 END), 0) as cash_sales
        FROM orders o
        LEFT JOIN bills bill ON o.order_id = bill.order_id
        WHERE o.branch_id = ?
        AND o.order_type != 'Customer Registration'
        AND o.order_status != 'Customer Created'
        AND o.order_status IN ('Bill Generated', 'Complete')
        AND (o.net_total_amount > 0 OR bill.grand_total > 0)
        $dateFilter
    ";
    
    $sales_stmt = mysqli_prepare($connection, $sales_sql);
    if (!$sales_stmt) {
        throw new Exception("Error preparing sales statement: " . mysqli_error($connection));
    }
    
    $sales_params = array_merge([$branch_id], $dateParams);
    $sales_types = 'i' . $dateTypes;
    mysqli_stmt_bind_param($sales_stmt, $sales_types, ...$sales_params);
    
    if (!mysqli_stmt_execute($sales_stmt)) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($sales_stmt);
        throw new Exception("Error executing sales query: " . $error);
    }
    
    $sales_result = mysqli_stmt_get_result($sales_stmt);
    $sales_data = mysqli_fetch_assoc($sales_result);
    mysqli_stmt_close($sales_stmt);
    
    // Get order statistics by status
    $orders_sql = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN LOWER(o.order_status) = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN LOWER(o.order_status) = 'preparing' THEN 1 END) as preparing,
            COUNT(CASE WHEN LOWER(o.order_status) = 'ready' THEN 1 END) as ready,
            COUNT(CASE WHEN LOWER(o.order_status) = 'confirmed' THEN 1 END) as confirmed,
            COUNT(CASE WHEN LOWER(o.order_status) IN ('complete', 'completed') THEN 1 END) as completed,
            COUNT(CASE WHEN LOWER(o.order_status) = 'delivered' THEN 1 END) as delivered,
            COUNT(CASE WHEN bill.payment_status = 'Paid' OR LOWER(bill.payment_status) = 'paid' THEN 1 END) as paid
        FROM orders o
        LEFT JOIN bills bill ON o.order_id = bill.order_id
        WHERE o.branch_id = ?
        AND o.order_type != 'Customer Registration'
        AND o.order_status != 'Customer Created'
        $dateFilter
    ";
    
    $orders_stmt = mysqli_prepare($connection, $orders_sql);
    if (!$orders_stmt) {
        throw new Exception("Error preparing orders statement: " . mysqli_error($connection));
    }
    
    $orders_params = array_merge([$branch_id], $dateParams);
    $orders_types = 'i' . $dateTypes;
    mysqli_stmt_bind_param($orders_stmt, $orders_types, ...$orders_params);
    
    if (!mysqli_stmt_execute($orders_stmt)) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($orders_stmt);
        throw new Exception("Error executing orders query: " . $error);
    }
    
    $orders_result = mysqli_stmt_get_result($orders_stmt);
    $orders_data = mysqli_fetch_assoc($orders_result);
    mysqli_stmt_close($orders_stmt);
    
    // Build response
    $response_data = [
        'branch_id' => $branch_id,
        'date' => $from_date && $to_date ? "$from_date to $to_date" : $date,
        'sales' => [
            'total' => floatval($sales_data['total_sales'] ?? 0),
            'net_total' => floatval($sales_data['net_total'] ?? 0),
            'grand_total' => floatval($sales_data['grand_total'] ?? 0),
            'amount' => floatval($sales_data['amount'] ?? 0),
            'total_orders' => intval($sales_data['total_orders'] ?? 0),
            'cash_sales' => floatval($sales_data['cash_sales'] ?? 0),
            'credit_sales' => floatval($sales_data['credit_sales'] ?? 0)
        ],
        'orders' => [
            'total' => intval($orders_data['total'] ?? 0),
            'pending' => intval($orders_data['pending'] ?? 0),
            'preparing' => intval($orders_data['preparing'] ?? 0),
            'ready' => intval($orders_data['ready'] ?? 0),
            'confirmed' => intval($orders_data['confirmed'] ?? 0),
            'completed' => intval($orders_data['completed'] ?? 0),
            'delivered' => intval($orders_data['delivered'] ?? 0),
            'paid' => intval($orders_data['paid'] ?? 0)
        ]
    ];
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(200);
    $response = json_encode([
        'success' => true,
        'data' => $response_data
    ], JSON_UNESCAPED_UNICODE);
    
    if ($response === false) {
        throw new Exception('JSON encoding failed: ' . json_last_error_msg());
    }
    
    echo $response;
    exit();
    
} catch (Exception $e) {
    error_log("Get Branch Daily Stats Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    error_log("Get Branch Daily Stats Fatal Error: " . $e->getMessage());
    
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
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

exit();
?>

