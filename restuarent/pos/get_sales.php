<?php
/**
 * Get Sales Data API
 * Fetches sales/orders data with optional date and terminal filtering
 * * POST Parameters:
 * - date1: (optional) Start date in YYYY-MM-DD format
 * - date2: (optional) End date in YYYY-MM-DD format
 * - terminal: (optional) Terminal ID to filter
 * - branch_id: (optional) Branch ID to filter
 * * Response:
 * - Array of order records with hall and table information
 */

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
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Fatal PHP error: ' . $error['message'],
            'error' => 'Fatal error in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit();
    }
});

require_once 'pos_init.php';

// Explicitly check connection after pos_init
if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection not available',
        'error' => 'Database connection failed'
    ]);
    exit();
}

// Test connection
if (!mysqli_ping($connection)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection is dead',
        'error' => 'Cannot ping database server'
    ]);
    exit();
}

try {
    // Get input data (supports both JSON and POST)
    $input = getPosRequestData();
    
    // Get the POST parameters
    $date1 = isset($input['date1']) ? trim($input['date1']) : null;
    $date2 = isset($input['date2']) ? trim($input['date2']) : null;
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 0;
    $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
    
    // Build SQL query with prepared statements
    $sql = "SELECT 
                orders.*, 
                COALESCE(halls.name, '') as hall_name, 
                COALESCE(tables.table_number, '') as table_no 
            FROM orders 
            LEFT JOIN tables ON orders.table_id = tables.table_id 
            LEFT JOIN halls ON halls.hall_id = COALESCE(tables.hall_id, orders.hall_id)
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Add date filter if provided
    if ($date1 && $date2) {
        // FIX: Enforce strict YYYY-MM-DD pattern validation and real-date checks
        $d1 = DateTime::createFromFormat('Y-m-d', $date1);
        $d2 = DateTime::createFromFormat('Y-m-d', $date2);
        
        if ($d1 && $d1->format('Y-m-d') === $date1 && $d2 && $d2->format('Y-m-d') === $date2) {
            // FIX: Index-friendly range check. Includes all records up to 23:59:59 of date2
            $sql .= " AND orders.created_at >= ? AND orders.created_at <= ?";
            $types .= 'ss';
            $params[] = $date1 . ' 00:00:00';
            $params[] = $date2 . ' 23:59:59';
        } else {
            throw new Exception("Invalid date format provided. Expected YYYY-MM-DD.");
        }
    }
    
    // Add terminal filter if provided
    if ($terminal > 0) {
        $sql .= " AND orders.terminal = ?";
        $types .= 'i';
        $params[] = $terminal;
    }
    
    // Add branch filter if provided
    if ($branch_id > 0) {
        $sql .= " AND orders.branch_id = ?";
        $types .= 'i';
        $params[] = $branch_id;
    }
    
    $sql .= " ORDER BY orders.order_id DESC";
    
    // Execute the query using prepared statement
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        $error = mysqli_error($connection);
        error_log("Get Sales - Prepare Error: " . $error);
        throw new Exception("Error preparing statement.");
    }
    
    if (!empty($types) && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        $stmt_error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        error_log("Get Sales - Statement Error: " . $stmt_error);
        throw new Exception("Error executing query.");
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if ($result === false) {
        $error = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        error_log("Get Sales - Get Result Error: " . $error);
        throw new Exception("Error getting database result configuration.");
    }
    
    // Create an array to hold the responses
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
    
    $json_output = json_encode($invoiceArray);
    if ($json_output === false) {
        error_log("Get Sales - JSON Encode Error: " . json_last_error_msg());
        echo json_encode(['status' => 'error', 'message' => 'Error encoding response payload']);
    } else {
        echo $json_output;
    }
    exit();
    
} catch (Throwable $e) { // FIX: Catches all Exceptions, Errors, and system crashes uniformly
    error_log("Get Sales Handler Error: " . $e->getMessage());
    error_log("Get Sales Error Trace: " . $e->getTraceAsString());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'An internal database or system error occurred.'
    ]);
    exit();
}
?>