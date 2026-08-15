<?php

/**
 * Day End Management API
 * Handles CREATE and UPDATE operations for day-end records
 * 
 * POST Parameters:
 * - id: (optional) Day-end ID. If empty, creates new record. If provided, updates existing.
 * - opening_balance: Opening balance for the day
 * - expences: Total expenses for the day
 * - total_cash: Total cash sales
 * - total_easypaisa: Total online/easypaisa sales
 * - total_bank: Total bank transfers
 * - credit_sales: Credit sales amount
 * - total_sales: Total sales amount
 * - total_receivings: Total receivings
 * - drawings: Drawings amount
 * - closing_balance: Closing balance
 * - closing_date_time: Closing date and time
 * - closing_by: User ID who closed the day
 * - note: Optional note
 * - branch_id: Branch ID (required)
 * 
 * Response:
 * - status: success/error
 * - message: Response message
 * - id: Day-end ID
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
        echo json_encode([
            "status" => "error",
            "success" => false,
            "message" => "Fatal error: " . $error['message']
        ], JSON_UNESCAPED_UNICODE);
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
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Configuration error: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Configuration error: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
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
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Database connection failed"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = null;
    
    // Try to decode JSON input
    if (!empty($input)) {
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // JSON decode failed, log the error
            error_log("Dayend JSON decode error: " . json_last_error_msg() . " | Input: " . substr($input, 0, 500));
            $data = null;
        }
    }
    
    // If JSON decode failed or no JSON input, try POST data
    if (!$data || !is_array($data)) {
        if (!empty($_POST)) {
            $data = $_POST;
        } else {
            // No data at all
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'No data received. Please send JSON or form data.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
    
    // Validate required fields
    if (!isset($data['branch_id']) || empty($data['branch_id'])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'Branch ID is required'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Get and sanitize data
    $id = isset($data['id']) && !empty($data['id']) ? intval($data['id']) : null;
    $branchId = intval($data['branch_id']);
    $openingBalance = isset($data['opening_balance']) ? floatval($data['opening_balance']) : 0;
    $expences = isset($data['expences']) ? floatval($data['expences']) : 0;
    $totalCash = isset($data['total_cash']) ? floatval($data['total_cash']) : 0;
    $totalEasypaisa = isset($data['total_easypaisa']) ? floatval($data['total_easypaisa']) : 0;
    $totalBank = isset($data['total_bank']) ? floatval($data['total_bank']) : 0;
    $creditSales = isset($data['credit_sales']) ? floatval($data['credit_sales']) : 0;
    $totalSales = isset($data['total_sales']) ? floatval($data['total_sales']) : 0;
    $totalReceivings = isset($data['total_receivings']) ? floatval($data['total_receivings']) : 0;
    $drawings = isset($data['drawings']) ? floatval($data['drawings']) : 0;
    $closingBalance = isset($data['closing_balance']) ? floatval($data['closing_balance']) : 0;
    $closingDateTime = isset($data['closing_date_time']) ? trim($data['closing_date_time']) : date('Y-m-d H:i:s');
    $closingBy = isset($data['closing_by']) ? intval($data['closing_by']) : 0;
    $note = isset($data['note']) ? trim($data['note']) : '';
    
    $createdAt = date('Y-m-d H:i:s');
    $updatedAt = date('Y-m-d H:i:s');
    
    // Auto-calculate closing balance if not provided (should be done before insert)
    if ($closingBalance == 0 && empty($id)) {
        // Will calculate after we have all the values
        $needsClosingBalanceCalc = true;
    } else {
        $needsClosingBalanceCalc = false;
    }
    
    // Auto-calculate sales data if not provided (only for new dayend records)
    if (empty($id)) {
        // Get last dayend closing_date_time for this branch to filter orders
        $last_dayend_date = null;
        $last_dayend_sql = "SELECT closing_date_time FROM dayend WHERE branch_id = ? ORDER BY closing_date_time DESC LIMIT 1";
        $last_dayend_stmt = mysqli_prepare($connection, $last_dayend_sql);
        if ($last_dayend_stmt) {
            mysqli_stmt_bind_param($last_dayend_stmt, "i", $branchId);
            if (mysqli_stmt_execute($last_dayend_stmt)) {
                $last_dayend_result = mysqli_stmt_get_result($last_dayend_stmt);
                $last_dayend_row = mysqli_fetch_assoc($last_dayend_result);
                if ($last_dayend_row && !empty($last_dayend_row['closing_date_time'])) {
                    $last_dayend_date = $last_dayend_row['closing_date_time'];
                }
                mysqli_free_result($last_dayend_result);
            }
            mysqli_stmt_close($last_dayend_stmt);
        }
        
        // Build date filter - orders after last dayend or today if no dayend exists
        $dateFilter = "";
        $salesParams = [];
        $salesTypes = "i";
        if ($last_dayend_date) {
            $dateFilter = " AND o.created_at > ?";
            $salesParams[] = $last_dayend_date;
            $salesTypes .= "s";
        } else {
            $dateFilter = " AND DATE(o.created_at) = CURDATE()";
        }
        
        // Fetch sales data from orders and bills tables
        // Only calculate if values are not provided (0 or empty)
        $autoCalculate = ($totalCash == 0 && $totalEasypaisa == 0 && $totalBank == 0 && $creditSales == 0 && $totalSales == 0);
        
        if ($autoCalculate) {
            // Calculate sales by payment method from orders and bills
            // Use bills.payment_method if available, otherwise fallback to orders.payment_mode
            $sales_sql = "
                SELECT 
                    COALESCE(SUM(CASE 
                        WHEN LOWER(TRIM(COALESCE(bill.payment_method, o.payment_mode, 'cash'))) IN ('cash', '') 
                        THEN COALESCE(bill.grand_total, o.net_total_amount, 0) 
                        ELSE 0 
                    END), 0) as total_cash,
                    COALESCE(SUM(CASE 
                        WHEN LOWER(TRIM(COALESCE(bill.payment_method, o.payment_mode, ''))) IN ('easypaisa', 'easypisa', 'online', 'upi', 'digital', 'card', 'debit', 'credit card', 'netbanking') 
                        THEN COALESCE(bill.grand_total, o.net_total_amount, 0) 
                        ELSE 0 
                    END), 0) as total_easypaisa,
                    COALESCE(SUM(CASE 
                        WHEN LOWER(TRIM(COALESCE(bill.payment_method, o.payment_mode, ''))) IN ('bank', 'transfer', 'cheque', 'check') 
                        THEN COALESCE(bill.grand_total, o.net_total_amount, 0) 
                        ELSE 0 
                    END), 0) as total_bank,
                    COALESCE(SUM(CASE 
                        WHEN LOWER(TRIM(COALESCE(bill.payment_method, o.payment_mode, ''))) IN ('credit', 'cred') 
                            OR (LOWER(TRIM(COALESCE(bill.payment_status, ''))) = 'unpaid' AND bill.customer_id IS NOT NULL)
                        THEN COALESCE(bill.grand_total, o.net_total_amount, 0) 
                        ELSE 0 
                    END), 0) as credit_sales,
                    COALESCE(SUM(COALESCE(bill.grand_total, o.net_total_amount, 0)), 0) as total_sales
                FROM orders o
                LEFT JOIN bills bill ON o.order_id = bill.order_id
                WHERE o.branch_id = ?
                AND o.order_type != 'Customer Registration'
                AND o.order_status != 'Customer Created'
                AND o.order_status IN ('Bill Generated', 'Complete')
                AND (o.net_total_amount > 0 OR bill.grand_total > 0)
                AND COALESCE(o.sts, 0) = 0
                " . $dateFilter . "
            ";
            
            $sales_stmt = mysqli_prepare($connection, $sales_sql);
            if ($sales_stmt) {
                if (!empty($salesParams)) {
                    mysqli_stmt_bind_param($sales_stmt, $salesTypes, $branchId, ...$salesParams);
                } else {
                    mysqli_stmt_bind_param($sales_stmt, "i", $branchId);
                }
                
                if (mysqli_stmt_execute($sales_stmt)) {
                    $sales_result = mysqli_stmt_get_result($sales_stmt);
                    $sales_row = mysqli_fetch_assoc($sales_result);
                    
                    if ($sales_row) {
                        // Override with calculated values if they were 0
                        if ($totalCash == 0) {
                            $totalCash = floatval($sales_row['total_cash'] ?? 0);
                        }
                        if ($totalEasypaisa == 0) {
                            $totalEasypaisa = floatval($sales_row['total_easypaisa'] ?? 0);
                        }
                        if ($totalBank == 0) {
                            $totalBank = floatval($sales_row['total_bank'] ?? 0);
                        }
                        if ($creditSales == 0) {
                            $creditSales = floatval($sales_row['credit_sales'] ?? 0);
                        }
                        if ($totalSales == 0) {
                            $totalSales = floatval($sales_row['total_sales'] ?? 0);
                        }
                        
                        // Log calculated values for debugging
                        error_log("Dayend Auto-Calculated Sales - Branch: $branchId, Cash: $totalCash, Easypaisa: $totalEasypaisa, Bank: $totalBank, Credit: $creditSales, Total: $totalSales");
                    }
                    
                    mysqli_free_result($sales_result);
                }
                mysqli_stmt_close($sales_stmt);
            }
            
            // Calculate expenses from expenses table (if not provided)
            if ($expences == 0) {
                $expenses_sql = "
                    SELECT COALESCE(SUM(amount), 0) as total_expenses
                    FROM expenses
                    WHERE branch_id = ?
                    " . ($last_dayend_date ? " AND created_at > ?" : " AND DATE(created_at) = CURDATE()");
                
                $expenses_stmt = mysqli_prepare($connection, $expenses_sql);
                if ($expenses_stmt) {
                    if ($last_dayend_date) {
                        mysqli_stmt_bind_param($expenses_stmt, "is", $branchId, $last_dayend_date);
                    } else {
                        mysqli_stmt_bind_param($expenses_stmt, "i", $branchId);
                    }
                    
                    if (mysqli_stmt_execute($expenses_stmt)) {
                        $expenses_result = mysqli_stmt_get_result($expenses_stmt);
                        $expenses_row = mysqli_fetch_assoc($expenses_result);
                        if ($expenses_row) {
                            $expences = floatval($expenses_row['total_expenses'] ?? 0);
                        }
                        mysqli_free_result($expenses_result);
                    }
                    mysqli_stmt_close($expenses_stmt);
                }
            }
        }
        
        // Auto-calculate closing balance now that we have all values
        if ($needsClosingBalanceCalc || $closingBalance == 0) {
            $closingBalance = $openingBalance + $totalSales + $totalReceivings - $expences - $drawings;
            error_log("Dayend Auto-Calculated Closing Balance: Opening($openingBalance) + Sales($totalSales) + Receivings($totalReceivings) - Expenses($expences) - Drawings($drawings) = $closingBalance");
        }
    }
    
    // Validate closing_date_time format
    if (!empty($closingDateTime) && !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $closingDateTime)) {
        // Try to parse and reformat
        $parsed = strtotime($closingDateTime);
        if ($parsed !== false) {
            $closingDateTime = date('Y-m-d H:i:s', $parsed);
        } else {
            $closingDateTime = date('Y-m-d H:i:s');
        }
    } elseif (empty($closingDateTime)) {
        $closingDateTime = date('Y-m-d H:i:s');
    }
    
    // Ensure note is not null
    if ($note === null) {
        $note = '';
    }
    
    if (empty($id)) {
        // Insert new day-end record
        // Validate all required numeric values are valid
        $values = [
            'branch_id' => $branchId,
            'opening_balance' => $openingBalance,
            'expences' => $expences,
            'total_cash' => $totalCash,
            'total_easypaisa' => $totalEasypaisa,
            'total_bank' => $totalBank,
            'credit_sales' => $creditSales,
            'total_sales' => $totalSales,
            'total_receivings' => $totalReceivings,
            'drawings' => $drawings,
            'closing_balance' => $closingBalance,
            'closing_by' => $closingBy
        ];
        
        // Log values for debugging
        error_log("Dayend Insert - Branch: $branchId, Values: " . json_encode($values));
        
        $stmt = mysqli_prepare($connection, "
            INSERT INTO dayend (
                branch_id, opening_balance, expences, total_cash, total_easypaisa, 
                total_bank, credit_sales, total_sales, total_receivings, drawings, 
                closing_balance, closing_date_time, closing_by, note, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            $error = mysqli_error($connection);
            error_log("Dayend Prepare Error: " . $error);
            throw new Exception("Error preparing insert statement: " . ($error ?: "Unknown database error"));
        }
        
        // Ensure all numeric values are properly cast
        $branchId = intval($branchId);
        $openingBalance = floatval($openingBalance);
        $expences = floatval($expences);
        $totalCash = floatval($totalCash);
        $totalEasypaisa = floatval($totalEasypaisa);
        $totalBank = floatval($totalBank);
        $creditSales = floatval($creditSales);
        $totalSales = floatval($totalSales);
        $totalReceivings = floatval($totalReceivings);
        $drawings = floatval($drawings);
        $closingBalance = floatval($closingBalance);
        $closingBy = intval($closingBy);
        
        mysqli_stmt_bind_param(
            $stmt,
            'iddddddddddssiss',
            $branchId, $openingBalance, $expences, $totalCash, $totalEasypaisa,
            $totalBank, $creditSales, $totalSales, $totalReceivings, $drawings,
            $closingBalance, $closingDateTime, $closingBy, $note, $createdAt, $updatedAt
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($connection);
            $stmt_error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            error_log("Dayend Execute Error - MySQL Error: " . $error . " | Statement Error: " . $stmt_error);
            throw new Exception("Error inserting day-end record: " . ($error ?: $stmt_error ?: "Unknown database error"));
        }
        
        $lastId = mysqli_insert_id($connection);
        if ($lastId <= 0) {
            mysqli_stmt_close($stmt);
            throw new Exception("Failed to get inserted dayend ID. The record may not have been created.");
        }
        mysqli_stmt_close($stmt);
        
        error_log("Dayend Created Successfully - ID: $lastId, Branch: $branchId");
        
        // Update orders.sts to the new dayend id (where sts = 0 for this branch)
        $ordersUpdated = 0;
        $updateOrders = mysqli_prepare($connection, "
            UPDATE orders 
            SET sts = ? 
            WHERE COALESCE(sts, 0) = 0 AND branch_id = ?
        ");
        
        if ($updateOrders) {
            mysqli_stmt_bind_param($updateOrders, 'ii', $lastId, $branchId);
            if (mysqli_stmt_execute($updateOrders)) {
                $ordersUpdated = mysqli_stmt_affected_rows($updateOrders);
                error_log("Dayend Orders Updated - Dayend ID: $lastId, Orders Affected: $ordersUpdated");
            } else {
                $update_error = mysqli_error($connection);
                error_log("Dayend Update Orders Error: " . $update_error);
            }
            mysqli_stmt_close($updateOrders);
        } else {
            $update_error = mysqli_error($connection);
            error_log("Dayend Prepare Update Orders Error: " . $update_error);
        }
        
        // Note: Unified expenses table structure doesn't have 'sts' column
        // The expenses table uses: id, title, amount, description, branch_id, terminal, created_at, updated_at
        // If you need to track expenses processed in day-end, consider using a different approach
        // such as storing dayend_id reference or using the created_at/updated_at timestamps
        
        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            'status' => 'success',
            'success' => true,
            'message' => 'Day-end record created successfully',
            'id' => $lastId,
            'data' => [
                'branch_id' => $branchId,
                'opening_balance' => $openingBalance,
                'expences' => $expences,
                'total_cash' => $totalCash,
                'total_easypaisa' => $totalEasypaisa,
                'total_bank' => $totalBank,
                'credit_sales' => $creditSales,
                'total_sales' => $totalSales,
                'total_receivings' => $totalReceivings,
                'drawings' => $drawings,
                'closing_balance' => $closingBalance,
                'closing_date_time' => $closingDateTime,
                'orders_updated' => $ordersUpdated
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // Update existing day-end record
        $stmt = mysqli_prepare($connection, "
            UPDATE dayend SET 
                opening_balance = ?,
                expences = ?,
                total_cash = ?,
                total_easypaisa = ?,
                total_bank = ?,
                credit_sales = ?,
                total_sales = ?,
                total_receivings = ?,
                drawings = ?,
                closing_balance = ?,
                closing_date_time = ?,
                closing_by = ?,
                note = ?,
                updated_at = ?
            WHERE id = ? AND branch_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Error preparing update statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param(
            $stmt,
            'ddddddddddssissi',
            $openingBalance, $expences, $totalCash, $totalEasypaisa,
            $totalBank, $creditSales, $totalSales, $totalReceivings, $drawings,
            $closingBalance, $closingDateTime, $closingBy, $note, $updatedAt,
            $id, $branchId
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($connection);
            mysqli_stmt_close($stmt);
            throw new Exception("Error updating day-end record: " . ($error ?: "Unknown error"));
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
                'status' => 'success',
                'success' => true,
                'message' => 'Day-end record updated successfully',
                'id' => $id
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Day-end record not found or no changes made'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    $error_trace = $e->getTraceAsString();
    
    error_log("Day End Management Error: " . $error_message);
    error_log("Day End Management Error Location: " . $error_file . ":" . $error_line);
    error_log("Day End Management Error Trace: " . $error_trace);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    // Determine if we're in production for error message display
    $is_production = !(
        $_SERVER['HTTP_HOST'] === 'localhost' || 
        $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
    );
    
    $user_message = $is_production 
        ? "Failed to mark day-end. Please check server logs for details."
        : $error_message;
    
    http_response_code(500);
    $response = [
        'status' => 'error',
        'success' => false,
        'message' => $user_message
    ];
    
    if (!$is_production) {
        $response['debug'] = [
            'error' => $error_message,
            'file' => $error_file,
            'line' => $error_line
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    $error_message = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    
    error_log("Day End Management Fatal Error: " . $error_message);
    error_log("Day End Management Fatal Error Location: " . $error_file . ":" . $error_line);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    $is_production = !(
        $_SERVER['HTTP_HOST'] === 'localhost' || 
        $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
    );
    
    $user_message = $is_production 
        ? "Failed to mark day-end due to a system error. Please contact support."
        : "Fatal error: " . $error_message;
    
    http_response_code(500);
    $response = [
        'status' => 'error',
        'success' => false,
        'message' => $user_message
    ];
    
    if (!$is_production) {
        $response['debug'] = [
            'error' => $error_message,
            'file' => $error_file,
            'line' => $error_line
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
} catch (Throwable $e) {
    $error_message = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    
    error_log("Day End Management Unknown Error: " . $error_message);
    error_log("Day End Management Unknown Error Location: " . $error_file . ":" . $error_line);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    $is_production = !(
        $_SERVER['HTTP_HOST'] === 'localhost' || 
        $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
    );
    
    $user_message = $is_production 
        ? "Failed to mark day-end due to an unknown error. Please contact support."
        : "Unknown error: " . $error_message;
    
    http_response_code(500);
    $response = [
        'status' => 'error',
        'success' => false,
        'message' => $user_message
    ];
    
    if (!$is_production) {
        $response['debug'] = [
            'error' => $error_message,
            'file' => $error_file,
            'line' => $error_line
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

exit();
?>

