<?php
/**
 * Save Day End Data
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
 * - branch_id: Branch ID (optional, for compatibility)
 * 
 * Response:
 * - status: success/error
 * - message: Response message
 * - id: Day-end ID
 */

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering
ob_start();

require_once '../api/cors_headers.php';
include("config.php"); // Include database connection

// Check connection
if (!isset($conn) || !$conn || $conn->connect_error) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Database connection failed"
    ]);
    exit;
}

try {
    // Get data from $_POST
    $id = isset($_POST['id']) && !empty($_POST['id']) ? intval($_POST['id']) : null;
    $branchId = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
    $opening_balance = isset($_POST['opening_balance']) ? floatval($_POST['opening_balance']) : 0;
    $expences = isset($_POST['expences']) ? floatval($_POST['expences']) : 0;
    $total_cash = isset($_POST['total_cash']) ? floatval($_POST['total_cash']) : 0;
    $total_easypaisa = isset($_POST['total_easypaisa']) ? floatval($_POST['total_easypaisa']) : 0;
    $total_bank = isset($_POST['total_bank']) ? floatval($_POST['total_bank']) : 0;
    $credit_sales = isset($_POST['credit_sales']) ? floatval($_POST['credit_sales']) : 0;
    $total_sales = isset($_POST['total_sales']) ? floatval($_POST['total_sales']) : 0;
    $total_receivings = isset($_POST['total_receivings']) ? floatval($_POST['total_receivings']) : 0;
    $drawings = isset($_POST['drawings']) ? floatval($_POST['drawings']) : 0;
    $closing_balance = isset($_POST['closing_balance']) ? floatval($_POST['closing_balance']) : 0;
    $closing_date_time = isset($_POST['closing_date_time']) ? trim($_POST['closing_date_time']) : date('Y-m-d H:i:s');
    $closing_by = isset($_POST['closing_by']) ? intval($_POST['closing_by']) : 0;
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    $created_at = date('Y-m-d H:i:s');
    $updated_at = date('Y-m-d H:i:s');

    if (empty($id)) {
        // Insert new record using prepared statement
        $stmt = $conn->prepare("INSERT INTO dayend (branch_id, opening_balance, expences, total_cash, total_easypaisa, total_bank, credit_sales, total_sales, total_receivings, drawings, closing_balance, closing_date_time, closing_by, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Error preparing insert statement: " . $conn->error);
        }
        
        $stmt->bind_param("iddddddddddssiss", 
            $branchId, $opening_balance, $expences, $total_cash, $total_easypaisa,
            $total_bank, $credit_sales, $total_sales, $total_receivings, $drawings,
            $closing_balance, $closing_date_time, $closing_by, $note, $created_at, $updated_at
        );
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Error inserting day-end record: " . $error);
        }
        
        $last_id = $conn->insert_id;
        $stmt->close();

        // Update `sts` in `orders` table (where sts = 0 for this branch)
        if ($branchId > 0) {
            $updateOrders = $conn->prepare("UPDATE orders SET sts = ? WHERE sts = 0 AND branch_id = ?");
            if ($updateOrders) {
                $updateOrders->bind_param("ii", $last_id, $branchId);
                $updateOrders->execute();
                $updateOrders->close();
            }
        } else {
            // Fallback: Update all orders with sts = 0 (for backward compatibility)
            $updateOrders = $conn->prepare("UPDATE orders SET sts = ? WHERE sts = 0");
            if ($updateOrders) {
                $updateOrders->bind_param("i", $last_id);
                $updateOrders->execute();
                $updateOrders->close();
            }
        }

        // Note: Unified expenses table doesn't have 'sts' column
        // Expense tracking is now handled through the unified expenses table structure
        // If you need to mark expenses as processed for day-end, consider using a different approach
        // such as storing dayend_id reference in expenses table or using a separate tracking table

        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "status" => "success", 
            "message" => "Record inserted successfully", 
            "id" => $last_id
        ]);
    } else {
        // Update existing record using prepared statement
        $stmt = $conn->prepare("UPDATE dayend SET opening_balance = ?, expences = ?, total_cash = ?, total_easypaisa = ?, total_bank = ?, credit_sales = ?, total_sales = ?, total_receivings = ?, drawings = ?, closing_balance = ?, closing_date_time = ?, closing_by = ?, note = ?, updated_at = ? WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }
        
        $stmt->bind_param("ddddddddddssisi",
            $opening_balance, $expences, $total_cash, $total_easypaisa,
            $total_bank, $credit_sales, $total_sales, $total_receivings, $drawings,
            $closing_balance, $closing_date_time, $closing_by, $note, $updated_at, $id
        );
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Error updating day-end record: " . $error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        // Clear buffer and output JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        if ($affected_rows > 0) {
            echo json_encode([
                "status" => "success", 
                "message" => "Record updated successfully", 
                "id" => $id
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error", 
                "message" => "Record not found or no changes made"
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("Save Day End Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Error: " . $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
