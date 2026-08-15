<?php
/**
 * Upload Expenses API (POS) - Uses Unified API Logic
 * 
 * This file provides POS-specific expense create/update
 * using the unified expenses table structure
 */

require_once '../api/cors_headers.php';
include("config.php");

// Disable error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Get POST data with backward compatibility mapping
$id = isset($_POST["id"]) ? trim($_POST["id"]) : '';
$title = isset($_POST["title"]) ? trim($_POST["title"]) : '';
$amount = isset($_POST["amount"]) ? floatval($_POST["amount"]) : (isset($_POST["price"]) ? floatval($_POST["price"]) : 0);
$description = isset($_POST["description"]) ? trim($_POST["description"]) : (isset($_POST["des"]) ? trim($_POST["des"]) : '');
$branch_id = isset($_POST["branch_id"]) && !empty($_POST["branch_id"]) ? intval($_POST["branch_id"]) : null;
$terminal = isset($_POST["terminal"]) ? intval($_POST["terminal"]) : 0;

$current_date = date("Y-m-d H:i:s");

// Validate required fields
if (empty($title)) {
    echo json_encode(['status' => 'error', 'message' => 'Title is required']);
    exit();
}

if ($amount < 0) {
    echo json_encode(['status' => 'error', 'message' => 'Valid amount is required']);
    exit();
}

if ($terminal <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Terminal is required']);
    exit();
}

try {
    if (empty($id)) {
        // INSERT new expense
        $sql = "INSERT INTO expenses (title, amount, description, branch_id, terminal, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "sdsiiss", $title, $amount, $description, $branch_id, $terminal, $current_date, $current_date);
        
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt);
            echo json_encode([
                'status' => 'success',
                'message' => 'Expense added successfully',
                'id' => $new_id
            ]);
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('Error: ' . $error);
        }
    } else {
        // UPDATE existing expense
        $expense_id = intval($id);
        
        $sql = "UPDATE expenses 
                SET title = ?, amount = ?, description = ?, branch_id = ?, terminal = ?, updated_at = ? 
                WHERE id = ?";
        
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "sdsiisi", $title, $amount, $description, $branch_id, $terminal, $current_date, $expense_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            if ($affected_rows > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Expense updated successfully',
                    'id' => $expense_id
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No expense found with the provided ID'
                ]);
            }
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('Error: ' . $error);
        }
    }
} catch (Exception $e) {
    error_log("Upload Expenses (POS) Error: " . $e->getMessage());
    
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($connection) && $connection) {
        mysqli_close($connection);
    }
}
exit();
?>
