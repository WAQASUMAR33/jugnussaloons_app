<?php
require_once 'cors_headers.php';
/**
 * Get Dashboard Statistics API
 * Returns statistics for admin dashboard
 * Supports both JSON and form data
 */

// Start output buffering to prevent HTML errors from breaking JSON
ob_start();

// Register shutdown function to handle errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean(); // Clear output buffer
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Server error occurred. Please check server logs.',
            'error' => $error['message']
        ]);
        exit();
    }
});

include("config.php");

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// Ensure connection is alive
if (isset($connection) && $connection) {
    if (!mysqli_ping($connection)) {
        ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            "success" => false, 
            "message" => "Database connection lost"
        ]);
        exit();
    }
}

// Get input data - handle both JSON and form data, and GET parameters
$input = [];

// For POST requests, try JSON body first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
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

// For GET requests, use query parameters
if ($_SERVER['REQUEST_METHOD'] === 'GET' || empty($input)) {
    if (!empty($_GET)) {
        $input = $_GET;
    }
}

// Get branch_id and terminal from input data
$branch_id = isset($input["branch_id"]) ? intval($input["branch_id"]) : null;
$terminal = isset($input["terminal"]) ? intval($input["terminal"]) : 1;

// Get last dayend closing_date_time for the branch to filter today's sales
$last_dayend_date = null;
if ($branch_id !== null && $branch_id > 0) {
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
}

// Build date filter for today's sales (after last dayend)
$date_filter = '';
if ($last_dayend_date) {
    $date_filter = " AND o.created_at > ?";
} else {
    // If no dayend exists, show only today's sales
    $date_filter = " AND DATE(o.created_at) = CURDATE()";
}

// Check connection - use isset to check if connection exists
try {
    if (!isset($connection) || !$connection) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode([
            "success" => false, 
            "message" => "Database connection failed"
        ]);
        exit();
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "message" => "Database connection error: " . $e->getMessage()
    ]);
    exit();
}

// Get statistics
$stats = [];

try {
    // Total Orders - Filter by branch_id and date (after last dayend)
    // Use same logic as sales: count only completed/billed orders after dayend
    // Join with bills table to ensure consistency with sales calculation
    $sql = "SELECT COUNT(DISTINCT o.order_id) as total 
            FROM orders o
            LEFT JOIN bills bill ON o.order_id = bill.order_id
            WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($branch_id !== null && $branch_id > 0) {
        $sql .= " AND o.branch_id = ?";
        $params[] = $branch_id;
        $types .= 'i';
    } else {
        $sql .= " AND o.terminal = ?";
        $params[] = $terminal;
        $types .= 'i';
    }
    
    // Add date filter for today's orders (after last dayend) - same as sales
    if ($last_dayend_date) {
        $sql .= " AND o.created_at > ?";
        $params[] = $last_dayend_date;
        $types .= 's';
    } else {
        $sql .= " AND DATE(o.created_at) = CURDATE()";
    }
    
    // Exclude registration orders and only count completed/billed orders (same as sales)
    $sql .= " AND o.order_type != 'Customer Registration' 
              AND o.order_status != 'Customer Created'
              AND o.order_status IN ('Bill Generated', 'Complete')
              AND (o.net_total_amount > 0 OR bill.grand_total > 0)";
    
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $stats['totalOrders'] = intval($row['total'] ?? 0);
        mysqli_stmt_close($stmt);
    } else {
        error_log('Failed to prepare statement for total orders: ' . mysqli_error($connection));
        $stats['totalOrders'] = 0;
    }
} catch (Exception $e) {
    error_log('Error getting total orders: ' . $e->getMessage());
    $stats['totalOrders'] = 0;
}

try {
    // Total Sales - Filter by branch_id and date (after last dayend)
    // Use bills.grand_total if available, otherwise orders.net_total_amount
    $sql = "SELECT COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) as total 
            FROM orders o
            LEFT JOIN bills bill ON o.order_id = bill.order_id
            WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($branch_id !== null && $branch_id > 0) {
        $sql .= " AND o.branch_id = ?";
        $params[] = $branch_id;
        $types .= 'i';
    } else {
        $sql .= " AND o.terminal = ?";
        $params[] = $terminal;
        $types .= 'i';
    }
    
    // Add date filter for today's sales (after last dayend)
    if ($last_dayend_date) {
        $sql .= " AND o.created_at > ?";
        $params[] = $last_dayend_date;
        $types .= 's';
    } else {
        $sql .= " AND DATE(o.created_at) = CURDATE()";
    }
    
    // Only count completed/billed orders
    $sql .= " AND o.order_type != 'Customer Registration' 
              AND o.order_status != 'Customer Created'
              AND o.order_status IN ('Bill Generated', 'Complete')
              AND (o.net_total_amount > 0 OR bill.grand_total > 0)";
    
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $stats['totalSales'] = floatval($row['total'] ?? 0);
        mysqli_stmt_close($stmt);
    } else {
        error_log('Failed to prepare statement for total sales: ' . mysqli_error($connection));
        $stats['totalSales'] = 0;
    }
} catch (Exception $e) {
    error_log('Error getting total sales: ' . $e->getMessage());
    $stats['totalSales'] = 0;
}

try {
    // Total Menu Items
    $sql = "SELECT COUNT(*) as total FROM dishes WHERE terminal = ?";
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $terminal);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $stats['totalMenuItems'] = intval($row['total'] ?? 0);
        mysqli_stmt_close($stmt);
    } else {
        error_log('Failed to prepare statement for total menu items: ' . mysqli_error($connection));
        $stats['totalMenuItems'] = 0;
    }
} catch (Exception $e) {
    error_log('Error getting total menu items: ' . $e->getMessage());
    $stats['totalMenuItems'] = 0;
}

try {
    // Total Categories
    $sql = "SELECT COUNT(*) as total FROM categories WHERE terminal = ?";
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $terminal);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $stats['totalCategories'] = intval($row['total'] ?? 0);
        mysqli_stmt_close($stmt);
    } else {
        error_log('Failed to prepare statement for total categories: ' . mysqli_error($connection));
        $stats['totalCategories'] = 0;
    }
} catch (Exception $e) {
    error_log('Error getting total categories: ' . $e->getMessage());
    $stats['totalCategories'] = 0;
}

try {
    // Recent Orders (last 5) - Filter by branch_id and date (after last dayend)
    // Use same date filtering as sales to ensure consistency
    $sql = "SELECT o.order_id, o.order_type, o.order_status, o.g_total_amount, o.net_total_amount, o.table_id, o.hall_id, o.created_at 
            FROM orders o
            WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($branch_id !== null && $branch_id > 0) {
        $sql .= " AND o.branch_id = ?";
        $params[] = $branch_id;
        $types .= 'i';
    } else {
        $sql .= " AND o.terminal = ?";
        $params[] = $terminal;
        $types .= 'i';
    }
    
    // Add date filter for today's orders (after last dayend) - same logic as sales
    if ($last_dayend_date) {
        $sql .= " AND o.created_at > ?";
        $params[] = $last_dayend_date;
        $types .= 's';
    } else {
        $sql .= " AND DATE(o.created_at) = CURDATE()";
    }
    
    // Exclude registration orders (recent orders can show all statuses for visibility)
    $sql .= " AND o.order_type != 'Customer Registration' AND o.order_status != 'Customer Created'
              ORDER BY o.created_at DESC 
              LIMIT 5";
    
    $stmt = mysqli_prepare($connection, $sql);
    $recentOrders = [];
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($result)) {
            $orderId = $row['order_id'] ?? $row['id'] ?? 0;
            $recentOrders[] = [
                'id' => $orderId,
                'order_number' => $orderId ? 'ORD-' . $orderId : 'ORD-0',
                'table_number' => $row['table_id'] ?? '-',
                'total' => floatval($row['net_total_amount'] ?? $row['g_total_amount'] ?? 0),
                'status' => ($row['order_status'] ?? 'Pending'),
                'shop_name' => '-', // No shop name available
                'created_at' => $row['created_at'] ?? '',
            ];
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log('Failed to prepare statement for recent orders: ' . mysqli_error($connection));
    }
} catch (Exception $e) {
    error_log('Error getting recent orders: ' . $e->getMessage());
    $recentOrders = [];
}

$stats['recentOrders'] = $recentOrders;

// Add todayOrders and todaySales for accountant dashboard compatibility
// These are the same as totalOrders and totalSales since they're already filtered for today
$stats['todayOrders'] = $stats['totalOrders'] ?? 0;
$stats['todaySales'] = $stats['totalSales'] ?? 0;

// Clean output buffer before sending JSON
ob_end_clean();

// Ensure JSON header is set
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

// Send JSON response
echo json_encode([
    "success" => true,
    "data" => $stats
], JSON_UNESCAPED_UNICODE);

// Close connection if it exists
if (isset($connection) && $connection) {
    mysqli_close($connection);
}
exit();
?>
