<?php
/**
 * Get Sales Report API (Updated)
 * - No date filtering
 * - period, from_date, to_date are removed
 * - Only fetch orders where sts = 0
 * - Optional branch_id filter
 */

require_once 'cors_headers.php';

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

ob_start();

// Load DB config
try {
    include("config.php");
} catch (Exception $e) {
    while (ob_get_level() > 0) ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit();
}

if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Read input (JSON or POST or GET)
$input = [];
$raw = file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) $input = $json;
}
if (empty($input) && !empty($_POST)) $input = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($input) && !empty($_GET)) $input = $_GET;

// Handle branch_id
$branch_id_value = isset($input['branch_id']) ? $input['branch_id'] : null;

if ($branch_id_value === null 
    || $branch_id_value === ''
    || $branch_id_value === 'null'
    || $branch_id_value === 'undefined'
    || intval($branch_id_value) <= 0) {
    $branch_id = null; // super admin (fetch all)
} else {
    $branch_id = intval($branch_id_value);
}

try {
    $conn = $connection;
    $sales = [];

    // Base WHERE (No date filters + sts = 0)
    $baseWhere = "
        o.sts = 0
        AND o.order_type != 'Customer Registration'
        AND o.order_status != 'Customer Created'
        AND o.order_status IN ('Bill Generated', 'Complete')
        AND (o.net_total_amount > 0 OR bill.grand_total > 0)
    ";

    // Add branch filter if needed
    $params = [];
    $types = '';

    if ($branch_id !== null) {
        $baseWhere .= " AND o.branch_id = ?";
        $params[] = $branch_id;
        $types .= 'i';
    }

    // Main Query (NO date filter)
    $sql = "
        SELECT 
            DATE(o.created_at) AS date,
            o.branch_id,
            COALESCE(br.branch_name, CONCAT('Branch ', o.branch_id)) AS branch_name,
            COUNT(DISTINCT o.order_id) AS total_orders,
            COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) AS total_sales,
            COALESCE(SUM(COALESCE(bill.total_amount, o.g_total_amount, 0)), 0) AS total_amount,
            COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) AS net_total,
            COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) AS grand_total,
            COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) AS amount,
            COALESCE(SUM(CASE WHEN LOWER(bill.payment_method) IN ('credit','cred')
                THEN COALESCE(bill.grand_total, o.net_total_amount, 0) ELSE 0 END), 0) AS credit_sales,
            COUNT(DISTINCT CASE WHEN LOWER(bill.payment_method) IN ('credit','cred')
                THEN o.order_id END) AS credit_orders,
            COALESCE(SUM(CASE WHEN LOWER(bill.payment_method) NOT IN ('credit','cred') 
                THEN COALESCE(bill.grand_total, o.net_total_amount, 0) ELSE 0 END), 0) AS cash_sales
        FROM orders o
        LEFT JOIN bills bill ON o.order_id = bill.order_id
        LEFT JOIN branches br ON o.branch_id = br.branch_id
        WHERE $baseWhere
        GROUP BY DATE(o.created_at), o.branch_id
        ORDER BY date DESC, o.branch_id ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("Prepare failed: " . mysqli_error($conn));

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_error($conn));
    }

    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $totalSales = floatval($row['total_sales']);
        $totalOrders = intval($row['total_orders']);
        $averageOrder = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;

        $sales[] = [
            'date' => $row['date'],
            'total_orders' => $totalOrders,
            'total' => $totalSales,
            'total_amount' => floatval($row['total_amount']),
            'net_total' => floatval($row['net_total']),
            'grand_total' => floatval($row['grand_total']),
            'amount' => floatval($row['amount']),
            'credit_sales' => floatval($row['credit_sales']),
            'credit_orders' => intval($row['credit_orders']),
            'cash_sales' => floatval($row['cash_sales']),
            'average_order' => $averageOrder,
            'branch_id' => intval($row['branch_id']),
            'branch_name' => $row['branch_name']
        ];
    }
    mysqli_stmt_close($stmt);

    while (ob_get_level() > 0) ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        'success' => true,
        'data' => $sales,
        'count' => count($sales),
        'message' => 'Sales data retrieved successfully'
    ]);

    exit();

} catch (Exception $e) {
    error_log("Sales API Error: " . $e->getMessage());
    while (ob_get_level() > 0) ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>
