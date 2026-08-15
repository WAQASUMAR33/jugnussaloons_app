<?php

/**
 * Get Day End Records API
 * Fetches day-end records from the database
 * 
 * POST Parameters:
 * - branch_id: (required) Branch ID to filter records
 * - start_date: (optional) Start date in YYYY-MM-DD format
 * - end_date: (optional) End date in YYYY-MM-DD format
 * 
 * Response:
 * - success: boolean
 * - data: array of day-end records
 */

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

require_once 'cors_headers.php';

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

// Support both GET and POST requests
$method = $_SERVER['REQUEST_METHOD'];

// Handle OPTIONS request for CORS
if ($method === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    exit();
}

if (!in_array($method, ['GET', 'POST', 'OPTIONS'])) {
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
    // Get input data - handle both JSON, POST, and GET
    $data = [];
    $raw_input = file_get_contents('php://input');
    
    if ($method === 'GET') {
        if (!empty($_GET)) {
            $data = $_GET;
        }
    } else {
        // For POST requests, try JSON body first
        if ($raw_input) {
            $json_input = json_decode($raw_input, true);
            if ($json_input && is_array($json_input)) {
                $data = $json_input;
            }
        }
        
        // Fallback to POST form data
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }
    }
    
    // Validate branch_id
    $branch_id_value = isset($data['branch_id']) ? $data['branch_id'] : null;
    
    // Handle empty, null, undefined, or 0 values
    if ($branch_id_value === null || $branch_id_value === '' || $branch_id_value === 'null' || $branch_id_value === 'undefined' || intval($branch_id_value) <= 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Branch ID is required and must be a positive integer'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $branchId = intval($data['branch_id']);
    $startDate = isset($data['start_date']) ? trim($data['start_date']) : null;
    $endDate = isset($data['end_date']) ? trim($data['end_date']) : null;
    
    // Validate date format if provided
    if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid start_date format. Use YYYY-MM-DD'
        ]);
        exit();
    }
    
    if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid end_date format. Use YYYY-MM-DD'
        ]);
        exit();
    }
    
    // Build query
    // Fixed: Use u.id instead of u.user_id (users table uses 'id' not 'user_id')
    $query = "
        SELECT 
            d.id,
            d.branch_id,
            COALESCE(b.branch_name, CONCAT('Branch ', d.branch_id)) AS branch_name,
            d.opening_balance,
            d.expences,
            d.total_cash,
            d.total_easypaisa,
            d.total_bank,
            d.credit_sales,
            d.total_sales,
            d.total_receivings,
            d.drawings,
            d.closing_balance,
            d.closing_date_time,
            d.closing_by,
            u.fullname AS closing_by_name,
            u.username AS closing_by_username,
            d.note,
            d.created_at,
            d.updated_at
        FROM dayend d
        LEFT JOIN branches b ON d.branch_id = b.branch_id
        LEFT JOIN users u ON d.closing_by = u.id
        WHERE d.branch_id = ?
    ";
    
    $params = [$branchId];
    $types = 'i';
    
    // Add date filters if provided
    if ($startDate) {
        $query .= " AND DATE(d.closing_date_time) >= ?";
        $params[] = $startDate;
        $types .= 's';
    }
    
    if ($endDate) {
        $query .= " AND DATE(d.closing_date_time) <= ?";
        $params[] = $endDate;
        $types .= 's';
    }
    
    $query .= " ORDER BY d.closing_date_time DESC, d.id DESC";
    
    // Prepare and execute statement
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
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
    
    $dayends = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $dayends[] = [
            'id' => intval($row['id']),
            'branch_id' => intval($row['branch_id']),
            'branch_name' => $row['branch_name'] ?? '',
            'opening_balance' => floatval($row['opening_balance']),
            'expences' => floatval($row['expences']),
            'total_cash' => floatval($row['total_cash']),
            'total_easypaisa' => floatval($row['total_easypaisa']),
            'total_bank' => floatval($row['total_bank']),
            'credit_sales' => floatval($row['credit_sales']),
            'total_sales' => floatval($row['total_sales']),
            'total_receivings' => floatval($row['total_receivings']),
            'drawings' => floatval($row['drawings']),
            'closing_balance' => floatval($row['closing_balance']),
            'closing_date_time' => $row['closing_date_time'] ?? null,
            'closing_by' => intval($row['closing_by'] ?? 0),
            'closing_by_name' => $row['closing_by_name'] ?? $row['closing_by_username'] ?? 'N/A',
            'note' => $row['note'] ?? '',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        'success' => true,
        'data' => $dayends,
        'count' => count($dayends)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Get Day End Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching day-end records: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    error_log("Get Day End Fatal Error: " . $e->getMessage());
    
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
} catch (Throwable $e) {
    error_log("Get Day End Unknown Error: " . $e->getMessage());
    
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
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

exit();
?>

