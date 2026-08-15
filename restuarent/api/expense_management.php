<?php
/**
 * Unified Expense Management API
 * Handles ALL expense operations using a single expenses table
 * 
 * This file handles all expense operations using the unified expenses table
 * 
 * GET/POST - Fetch expenses or expense titles/categories
 *   - action=categories&terminal=1 - Get unique expense titles/categories
 *   - date1&date2 - Filter expenses by date range (YYYY-MM-DD)
 *   - branch_id - Filter by branch_id
 *   - terminal - Filter by terminal
 *   - No params - Get all expenses
 * 
 * POST - Create or Update expense (when id is provided or not)
 *   - id: (optional) Expense ID. If empty, creates new expense. If provided, updates existing.
 *   - title: (required) Expense title/category
 *   - amount: (required) Expense amount
 *   - description: (optional) Expense description
 *   - branch_id: (optional) Branch ID
 *   - terminal: (required) Terminal ID
 * 
 * DELETE - Delete expense
 *   - id: (required) Expense ID to delete
 * 
 * Response Format:
 * - success: boolean
 * - message: string
 * - data: array (for GET requests)
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
    
    // Get detailed error message
    $error_msg = "Database connection failed";
    $connect_error = mysqli_connect_error();
    if ($connect_error) {
        $error_msg = "Database connection failed: " . $connect_error;
        
        // Provide helpful message for common errors
        if (strpos($connect_error, "Access denied") !== false) {
            $error_msg = "Database access denied. Please check your database credentials in config.php. " . 
                        "If MySQL requires a password, update DB_PASS in config.php";
        } elseif (strpos($connect_error, "Unknown database") !== false) {
            $error_msg = "Database not found. Please check DB_NAME in config.php";
        }
    }
    
    echo json_encode(["success" => false, "message" => $error_msg]);
    exit();
}

// Verify database connection is alive
if (!mysqli_ping($connection)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Database connection lost. Please try again."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Read and cache JSON input for POST requests (can only be read once)
$cachedJsonInput = null;
$cachedJsonData = null;
if ($method === 'POST') {
    $cachedJsonInput = file_get_contents('php://input');
    $cachedJsonData = json_decode($cachedJsonInput, true);
}

// Support both GET and POST for fetching expenses
try {
    // Determine if POST request is for fetching or creating/updating
    $isFetchRequest = false;
    if ($method === 'GET') {
        $isFetchRequest = true;
    } elseif ($method === 'POST') {
        // Check if it's a create/update request by looking for 'id' or 'title'
        if ($cachedJsonData && is_array($cachedJsonData)) {
            // If it has 'id' or 'title', it's a create/update request, not fetch
            $isFetchRequest = !isset($cachedJsonData['id']) && !isset($cachedJsonData['title']);
        } else {
            // Form POST request - check $_POST
            $isFetchRequest = !isset($_POST['id']) && !isset($_POST['title']);
        }
    }
    
    if ($isFetchRequest) {
        // ============================================
        // GET/POST REQUEST - Fetch expenses or categories
        // ============================================
        // Get data from GET or POST
        $input = ($method === 'GET') ? $_GET : $_POST;
        
        // Also check JSON body for POST requests (use cached data)
        if ($method === 'POST' && ($cachedJsonData && is_array($cachedJsonData))) {
            $input = $cachedJsonData;
        }
        
        $action = isset($input['action']) ? trim($input['action']) : '';
        $branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : null;
        $terminal = isset($input['terminal']) ? intval($input['terminal']) : 0;
        $date1 = isset($input['date1']) ? trim($input['date1']) : null;
        $date2 = isset($input['date2']) ? trim($input['date2']) : null;
        
        // Handle branch_id
        $branch_id = null;
        if ($branch_id_input !== null && $branch_id_input !== '' && $branch_id_input !== 'null' && $branch_id_input !== 'undefined') {
            $branch_id = intval($branch_id_input);
            if ($branch_id <= 0) {
                $branch_id = null;
            }
        }
        
        if ($action === 'categories' || $action === 'titles') {
            // Get unique expense titles/categories
            if ($terminal <= 0) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Content-Type: application/json; charset=UTF-8");
                }
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Terminal is required for fetching categories'
                ]);
                exit();
            }
            
            // Get distinct titles from expenses table for the given terminal
            $sql = "SELECT DISTINCT title FROM expenses WHERE terminal = ? AND title IS NOT NULL AND title != '' ORDER BY title ASC";
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, 'i', $terminal);
            
            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_error($connection);
                mysqli_stmt_close($stmt);
                throw new Exception("Error fetching categories: " . ($error ?: "Unknown error"));
            }
            
            $result = mysqli_stmt_get_result($stmt);
            $categories = [];
            
            while ($row = mysqli_fetch_assoc($result)) {
                $categories[] = [
                    'title' => $row['title'],
                    'terminal' => $terminal
                ];
            }
            
            mysqli_stmt_close($stmt);
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            $response = json_encode([
                'success' => true,
                'data' => $categories,
                'count' => count($categories)
            ]);
            
            if ($response === false) {
                throw new Exception('JSON encoding failed: ' . json_last_error_msg());
            }
            
            echo $response;
            exit();
            
        } else {
            // Get expenses list
            $sql = "SELECT 
                        e.id,
                        e.title,
                        e.amount,
                        e.description,
                        e.branch_id,
                        e.terminal,
                        e.created_at,
                        e.updated_at,
                        b.branch_name
                    FROM expenses e
                    LEFT JOIN branches b ON e.branch_id = b.branch_id
                    WHERE 1=1";
            
            $params = [];
            $types = "";
            
            // Filter by branch_id if provided
            if ($branch_id !== null) {
                $sql .= " AND e.branch_id = ?";
                $params[] = $branch_id;
                $types .= "i";
            }
            
            // Filter by terminal if provided
            if ($terminal > 0) {
                $sql .= " AND e.terminal = ?";
                $params[] = $terminal;
                $types .= "i";
            }
            
            // Filter by date range if provided (for POS compatibility)
            if ($date1 && $date2 && preg_match('/^\d{4}-\d{2}-\d{2}/', $date1) && preg_match('/^\d{4}-\d{2}-\d{2}/', $date2)) {
                $sql .= " AND DATE(e.created_at) BETWEEN ? AND ?";
                $params[] = $date1;
                $params[] = $date2;
                $types .= "ss";
            }
            
            // Only get expenses with amount > 0 (exclude category-only entries if needed)
            // Uncomment below if you want to filter out category entries
            // $sql .= " AND e.amount > 0";
            
            $sql .= " ORDER BY e.created_at DESC, e.id DESC";
            
            $stmt = mysqli_prepare($connection, $sql);
            
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
                throw new Exception("Error getting result: " . mysqli_error($connection));
            }
            
            $expenses = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $expenses[] = [
                    'id' => intval($row['id']),
                    'title' => $row['title'] ?? '',
                    'amount' => floatval($row['amount'] ?? 0),
                    'description' => $row['description'] ?? '',
                    'branch_id' => $row['branch_id'] ? intval($row['branch_id']) : null,
                    'branch_name' => $row['branch_name'] ?? null,
                    'terminal' => intval($row['terminal'] ?? 1),
                    'created_at' => $row['created_at'] ?? null,
                    'updated_at' => $row['updated_at'] ?? null
                ];
            }
            
            mysqli_stmt_close($stmt);
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            $response = json_encode([
                'success' => true,
                'data' => $expenses,
                'count' => count($expenses)
            ]);
            
            if ($response === false) {
                throw new Exception('JSON encoding failed: ' . json_last_error_msg());
            }
            
            echo $response;
            exit();
        }
        
    } else if ($method === 'DELETE') {
        // ============================================
        // DELETE REQUEST - Delete expense
        // ============================================
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !is_array($data)) {
            $data = $_POST;
        }
        
        if (!isset($data['id']) || empty($data['id'])) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Expense ID is required for deletion'
            ]);
            exit();
        }
        
        $id = intval($data['id']);
        
        if ($id <= 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid expense ID'
            ]);
            exit();
        }
        
        // Delete expense
        $stmt = mysqli_prepare($connection, "DELETE FROM expenses WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Error preparing delete statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($connection);
            mysqli_stmt_close($stmt);
            throw new Exception("Error deleting expense: " . ($error ?: "Unknown error"));
        }
        
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        if ($affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Expense deleted successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Expense not found'
            ]);
        }
        exit();
        
    } else if ($method === 'POST') {
        // ============================================
        // POST REQUEST - Create or Update expense
        // ============================================
        // Use cached JSON data (already read above)
        $data = $cachedJsonData;
        
        // If JSON decode failed or is not an array, try $_POST (form data)
        if (!$data || !is_array($data)) {
            $data = $_POST;
        }
        
        // If still no data, return error
        if (empty($data) || !is_array($data)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid request data. Please provide expense data in JSON format or form data.'
            ]);
            exit();
        }
        
        // Log received data for debugging (remove in production)
        error_log("Expense POST Request Data: " . json_encode($data));
        
        // Check if required columns exist in database
        $check_columns = mysqli_query($connection, "SHOW COLUMNS FROM expenses LIKE 'title'");
        if (!$check_columns || mysqli_num_rows($check_columns) == 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database schema error: expenses table is missing required columns (title, terminal). Please run the migration SQL script to update the table structure.'
            ]);
            exit();
        }
        
        // Validate required fields
        if (!isset($data['title']) || empty(trim($data['title']))) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Expense title is required'
            ]);
            exit();
        }
        
        if (!isset($data['amount']) || empty($data['amount']) || floatval($data['amount']) < 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Valid amount is required'
            ]);
            exit();
        }
        
        if (!isset($data['terminal']) || empty($data['terminal']) || intval($data['terminal']) <= 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Terminal is required'
            ]);
            exit();
        }
        
        $id = isset($data['id']) && !empty($data['id']) ? intval($data['id']) : null;
        $title = trim($data['title']);
        $amount = floatval($data['amount']);
        $description = isset($data['description']) ? trim($data['description']) : '';
        $branch_id = isset($data['branch_id']) && !empty($data['branch_id']) ? intval($data['branch_id']) : null;
        $terminal = intval($data['terminal']);
        
        if ($id && $id > 0) {
            // UPDATE existing expense
            $stmt = mysqli_prepare($connection, "
                UPDATE expenses 
                SET title = ?, 
                    amount = ?, 
                    description = ?,
                    branch_id = ?,
                    terminal = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, 'sdsiii', $title, $amount, $description, $branch_id, $terminal, $id);
            
            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_error($connection);
                mysqli_stmt_close($stmt);
                
                // Log the detailed error for debugging
                error_log("Expense UPDATE Error: " . $error);
                
                // Check for common database errors
                if (strpos($error, "Unknown column") !== false) {
                    throw new Exception("Database schema error: " . $error . " Please run the migration SQL script to update the table structure.");
                } else {
                    throw new Exception("Error updating expense: " . ($error ?: "Unknown database error"));
                }
            }
            
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            // Clear buffer and output JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            if ($affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Expense updated successfully',
                'id' => $id
            ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Expense not found or no changes made'
                ]);
            }
            exit();
            
        } else {
            // CREATE new expense
            $stmt = mysqli_prepare($connection, "
                INSERT INTO expenses (title, amount, description, branch_id, terminal, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            if (!$stmt) {
                throw new Exception("Error preparing insert statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt, 'sdsii', $title, $amount, $description, $branch_id, $terminal);
            
            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_error($connection);
                mysqli_stmt_close($stmt);
                
                // Log the detailed error for debugging
                error_log("Expense INSERT Error: " . $error);
                error_log("Expense Data: " . json_encode([
                    'title' => $title,
                    'amount' => $amount,
                    'description' => $description,
                    'branch_id' => $branch_id,
                    'terminal' => $terminal
                ]));
                
                // Check for common database errors
                if (strpos($error, "Unknown column") !== false) {
                    throw new Exception("Database schema error: " . $error . " Please run the migration SQL script to update the table structure.");
                } elseif (strpos($error, "Column") !== false && strpos($error, "cannot be null") !== false) {
                    throw new Exception("Required field missing: " . $error);
                } else {
                    throw new Exception("Error creating expense: " . ($error ?: "Unknown database error"));
                }
            }
            
            $newId = mysqli_insert_id($connection);
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
                'message' => 'Expense created successfully',
                'id' => $newId
            ]);
            exit();
        }
        exit();
        
    } else {
        // Method not allowed
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use GET, POST, or DELETE.'
        ]);
    }
    
} catch (Exception $e) {
    $error_msg = $e->getMessage();
    error_log("Expense Management Error: " . $error_msg);
    error_log("Expense Management Error Stack: " . $e->getTraceAsString());
    
    // Check if error is related to database connection
    $error_message = $error_msg ?: "An unknown error occurred";
    $is_db_error = false;
    
    // Check if connection is still valid
    if (isset($connection) && $connection) {
        if (!mysqli_ping($connection)) {
            $is_db_error = true;
            $error_message = "Database connection lost during operation. Please try again.";
        } else {
            $mysql_error = mysqli_error($connection);
            if (!empty($mysql_error)) {
                $is_db_error = true;
                
                // Provide helpful messages for common database errors
                if (strpos($mysql_error, "Lost connection") !== false || 
                    strpos($mysql_error, "MySQL server has gone away") !== false) {
                    $error_message = "Database connection lost. Please try again.";
                } elseif (strpos($mysql_error, "Unknown column") !== false ||
                          strpos($mysql_error, "Table") !== false && strpos($mysql_error, "doesn't exist") !== false) {
                    $error_message = "Database schema error: " . $mysql_error . ". Please run the migration SQL script.";
                } else {
                    $error_message = "Database error: " . $mysql_error;
                }
            }
        }
    }
    
    // If it's a connection error, use the specific message; otherwise use the exception message
    if (!$is_db_error && (
        strpos($error_message, "Connection") !== false ||
        strpos($error_message, "connection") !== false ||
        strpos($error_message, "connect") !== false
    )) {
        $is_db_error = true;
        $error_message = "Database connection error: " . $error_message;
    }
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => $error_message
    ];
    
    // Add debug info if available (remove in production)
    if (isset($data)) {
        $response['debug'] = [
            'received_data' => $data
        ];
    }
    
    echo json_encode($response);
    exit();
} catch (Error $e) {
    error_log("Expense Management Fatal Error: " . $e->getMessage());
    
    // Check if connection exists and is valid
    $error_message = $e->getMessage();
    if (isset($connection) && $connection && !mysqli_ping($connection)) {
        $error_message = "Database connection lost. Please try again.";
    } elseif (isset($connection) && $connection) {
        $mysql_error = mysqli_error($connection);
        if (!empty($mysql_error)) {
            if (strpos($mysql_error, "Lost connection") !== false || 
                strpos($mysql_error, "MySQL server has gone away") !== false) {
                $error_message = "Database connection lost. Please try again.";
            } else {
                $error_message = "Database error: " . $mysql_error;
            }
        }
    }
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $error_message
    ]);
    exit();
}

exit();
?>
