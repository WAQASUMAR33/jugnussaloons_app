<?php
/**
 * Get Sales Data (Running/Generate Bill Orders)
 * Fetches sales/orders data for running or bill generation status
 * * POST Parameters:
 * - date1: (optional) Start date in YYYY-MM-DD format
 * - date2: (optional) End date in YYYY-MM-DD format
 * - terminal: (optional) Terminal ID to filter
 * * Response:
 * - Array of order records with hall and table information
 */

require_once 'pos_init.php';

// Check connection instance before processing
if (!isset($connection) || !$connection) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection unavailable.']);
    exit();
}

try {
    // Force POST method check
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. Use POST.']);
        exit();
    }

    // Get the POST parameters
    $date1 = isset($_POST['date1']) ? trim($_POST['date1']) : null;
    $date2 = isset($_POST['date2']) ? trim($_POST['date2']) : null;
    $terminal = isset($_POST['terminal']) ? intval($_POST['terminal']) : null;
    
    // Build SQL query with prepared statements
    // FIX: Removed orders.terminal = tables.terminal constraint to fix table identification across devices
    $sql = "SELECT orders.*, COALESCE(halls.name, '') as hall_name, COALESCE(tables.table_number, '') as table_no 
            FROM orders 
            LEFT JOIN tables ON orders.table_id = tables.table_id AND orders.branch_id = tables.branch_id
            LEFT JOIN halls ON halls.hall_id = tables.hall_id 
            WHERE (orders.order_status = 'Running' OR orders.order_status = 'Generate Bill')
            AND orders.order_type != 'Customer Registration'
            AND orders.order_status != 'Customer Created'";
    
    $params = [];
    $types = '';
    
    // Add date filter if provided
    if ($date1 && $date2) {
        // FIX: Replaced unreliable regex verification with explicit DateTime constraints
        $d1 = DateTime::createFromFormat('Y-m-d', $date1);
        $d2 = DateTime::createFromFormat('Y-m-d', $date2);
        
        if ($d1 && $d1->format('Y-m-d') === $date1 && $d2 && $d2->format('Y-m-d') === $date2) {
            // FIX: Index-friendly range scan using literal boundaries up to the last second of day2
            $sql .= " AND orders.created_at >= ? AND orders.created_at <= ?";
            $types .= 'ss';
            $params[] = $date1 . ' 00:00:00';
            $params[] = $date2 . ' 23:59:59';
        } else {
            header("Content-Type: application/json; charset=UTF-8");
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid format provided for date filters.']);
            exit();
        }
    }
    
    // Add terminal filter if provided
    if ($terminal !== null && $terminal > 0) {
        $sql .= " AND orders.terminal = ?";
        $types .= 'i';
        $params[] = $terminal;
    }
    
    $sql .= " ORDER BY orders.order_id DESC";
    
    // Execute the query using prepared statement
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        error_log("Prepare Error: " . mysqli_error($connection));
        throw new Exception("Internal server error during statement construction.");
    }
    
    if (!empty($types) && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        error_log("Execution Error: " . $error);
        throw new Exception("Internal server error during query execution.");
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $invoiceArray = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $invoiceArray[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(200);
    echo json_encode($invoiceArray);
    exit();
    
} catch (Throwable $e) { // FIX: Captures both standard Errors and Runtime Exceptions elegantly
    error_log("Get Running Orders Handler Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'An internal database or backend system error occurred.'
    ]);
    exit();
} finally {
    if (isset($connection) && $connection) {
        mysqli_close($connection);
    }
}
?>