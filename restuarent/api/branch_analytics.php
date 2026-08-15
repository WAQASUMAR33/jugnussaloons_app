<?php

/**
 * Branch Analytics API
 * Returns analytics data for branches (sales, orders, expenses)
 * 
 * GET Parameters:
 * - branch_id (int, optional) - If provided, returns data for that branch only
 * - date_from (string, optional) - YYYY-MM-DD format
 * - date_to (string, optional) - YYYY-MM-DD format
 * 
 * Response includes:
 * - Total sales
 * - Total orders
 * - Total expenses
 * - Daily/monthly breakdown
 * - Top selling items
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

try {
    $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
    
    $analytics = [];
    
    // Build WHERE clause
    $where_clause = "WHERE DATE(o.created_at) BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    $types = "ss";
    
    if ($branch_id > 0) {
        $where_clause .= " AND o.branch_id = ?";
        $params[] = $branch_id;
        $types .= "i";
    }
    
    // Total Sales
    $sales_sql = "SELECT 
                    COALESCE(SUM(b.grand_total), 0) as total_sales,
                    COUNT(DISTINCT b.bill_id) as total_bills,
                    COUNT(DISTINCT o.order_id) as total_orders
                  FROM orders o
                  LEFT JOIN bills b ON o.order_id = b.order_id
                  $where_clause";
    
    $stmt = mysqli_prepare($connection, $sales_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sales_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $analytics['total_sales'] = floatval($sales_data['total_sales'] ?? 0);
    $analytics['total_bills'] = intval($sales_data['total_bills'] ?? 0);
    $analytics['total_orders'] = intval($sales_data['total_orders'] ?? 0);
    
    // Daily Sales Breakdown
    $daily_sql = "SELECT 
                    DATE(o.created_at) as date,
                    COALESCE(SUM(b.grand_total), 0) as sales,
                    COUNT(DISTINCT o.order_id) as orders
                  FROM orders o
                  LEFT JOIN bills b ON o.order_id = b.order_id
                  $where_clause
                  GROUP BY DATE(o.created_at)
                  ORDER BY date ASC";
    
    $stmt = mysqli_prepare($connection, $daily_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $daily_breakdown = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $daily_breakdown[] = [
            'date' => $row['date'],
            'sales' => floatval($row['sales']),
            'orders' => intval($row['orders'])
        ];
    }
    mysqli_stmt_close($stmt);
    
    $analytics['daily_breakdown'] = $daily_breakdown;
    
    // Top Selling Items
    $top_items_sql = "SELECT 
                        d.dish_name,
                        SUM(od.quantity) as total_quantity,
                        SUM(od.quantity * od.price) as total_revenue
                      FROM orderdetails od
                      INNER JOIN orders o ON od.order_id = o.order_id
                      INNER JOIN dishes d ON od.dish_id = d.dish_id
                      WHERE DATE(o.created_at) BETWEEN ? AND ?";
    
    if ($branch_id > 0) {
        $top_items_sql .= " AND o.branch_id = ?";
    }
    
    $top_items_sql .= " GROUP BY d.dish_id, d.dish_name
                        ORDER BY total_quantity DESC
                        LIMIT 10";
    
    $stmt = mysqli_prepare($connection, $top_items_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $top_items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $top_items[] = [
            'dish_name' => $row['dish_name'],
            'total_quantity' => intval($row['total_quantity']),
            'total_revenue' => floatval($row['total_revenue'])
        ];
    }
    mysqli_stmt_close($stmt);
    
    $analytics['top_items'] = $top_items;
    
    // Total Expenses
    $expenses_where = "WHERE DATE(created_at) BETWEEN ? AND ?";
    $expenses_params = [$date_from, $date_to];
    $expenses_types = "ss";
    
    if ($branch_id > 0) {
        $expenses_where .= " AND branch_id = ?";
        $expenses_params[] = $branch_id;
        $expenses_types .= "i";
    }
    
    $expenses_sql = "SELECT COALESCE(SUM(amount), 0) as total_expenses
                     FROM expenses
                     $expenses_where";
    
    $stmt = mysqli_prepare($connection, $expenses_sql);
    mysqli_stmt_bind_param($stmt, $expenses_types, ...$expenses_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $expenses_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $analytics['total_expenses'] = floatval($expenses_data['total_expenses'] ?? 0);
    $analytics['net_profit'] = $analytics['total_sales'] - $analytics['total_expenses'];
    
    // Branch-wise summary (if no specific branch requested)
    if ($branch_id == 0) {
        $branch_summary_sql = "SELECT 
                                b.branch_id,
                                b.branch_name,
                                b.branch_code,
                                COALESCE(SUM(bills.grand_total), 0) as sales,
                                COUNT(DISTINCT orders.order_id) as orders
                              FROM branches b
                              LEFT JOIN orders ON b.branch_id = orders.branch_id AND DATE(orders.created_at) BETWEEN ? AND ?
                              LEFT JOIN bills ON orders.order_id = bills.order_id
                              GROUP BY b.branch_id, b.branch_name, b.branch_code
                              ORDER BY sales DESC";
        
        $stmt = mysqli_prepare($connection, $branch_summary_sql);
        mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $branch_summary = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $branch_summary[] = [
                'branch_id' => intval($row['branch_id']),
                'branch_name' => $row['branch_name'],
                'branch_code' => $row['branch_code'],
                'sales' => floatval($row['sales']),
                'orders' => intval($row['orders'])
            ];
        }
        mysqli_stmt_close($stmt);
        
        $analytics['branch_summary'] = $branch_summary;
    }
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => true,
        "data" => $analytics,
        "date_from" => $date_from,
        "date_to" => $date_to,
        "branch_id" => $branch_id > 0 ? $branch_id : null
    ]);
    
} catch (Exception $e) {
    error_log("Branch Analytics Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
}

exit();
?>

