<?php
/**
 * Change Order Status API
 * Updates order status and payment status
 * Handles bill payment and order completion
 */
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
        echo json_encode(["success" => false, "message" => "Fatal error: " . $error['message']]);
        exit();
    }
});

// Start output buffering
ob_start();

// Include config
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

// Get input data - handle both JSON and form data
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (!$input || !is_array($input)) {
    $input = $_POST;
}

// Also merge GET parameters if needed
if (empty($input) || !is_array($input)) {
    $input = array_merge($_GET, $_POST);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Only handle POST requests
    if ($method !== 'POST') {
        throw new Exception("Only POST method is allowed");
    }
    
    // Get required parameters - handle multiple possible field names
    $order_id = isset($input['order_id']) ? intval($input['order_id']) : (isset($input['id']) ? intval($input['id']) : 0);
    $order_status = isset($input['order_status']) && trim($input['order_status']) !== '' ? trim($input['order_status']) : null;
    $payment_status = isset($input['payment_status']) && trim($input['payment_status']) !== '' ? trim($input['payment_status']) : null;
    $payment_method = null;
    
    // Try multiple possible field names for payment_method
    if (isset($input['payment_method']) && trim($input['payment_method']) !== '') {
        $payment_method = trim($input['payment_method']);
    } elseif (isset($input['payment_mode']) && trim($input['payment_mode']) !== '') {
        $payment_method = trim($input['payment_mode']);
    } elseif (isset($input['paymentMethod']) && trim($input['paymentMethod']) !== '') {
        $payment_method = trim($input['paymentMethod']);
    } elseif (isset($input['paymentMode']) && trim($input['paymentMode']) !== '') {
        $payment_method = trim($input['paymentMode']);
    }
    
    // Log received data for debugging
    error_log("Change Order Status - Received data: " . json_encode([
        'method' => $method,
        'order_id' => $order_id,
        'order_status' => $order_status,
        'payment_status' => $payment_status,
        'payment_method' => $payment_method,
        'raw_input' => $raw_input,
        'all_input' => $input,
        'POST' => $_POST,
        'GET' => $_GET
    ]));
    
    // Validate order_id
    if (empty($order_id) || $order_id <= 0) {
        // Try to get from URL parameters or other sources
        if (isset($_GET['order_id'])) {
            $order_id = intval($_GET['order_id']);
        }
        if (empty($order_id) || $order_id <= 0) {
            throw new Exception("Order ID is required. Received: " . json_encode($input));
        }
    }
    
    // Check if order exists
    $check_order_sql = "SELECT order_id, order_status, customer_id FROM orders WHERE order_id = ?";
    $check_order_stmt = mysqli_prepare($connection, $check_order_sql);
    
    if (!$check_order_stmt) {
        throw new Exception("Error checking order: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($check_order_stmt, "i", $order_id);
    mysqli_stmt_execute($check_order_stmt);
    $order_result = mysqli_stmt_get_result($check_order_stmt);
    $existing_order = mysqli_fetch_assoc($order_result);
    mysqli_stmt_close($check_order_stmt);
    
    if (!$existing_order) {
        throw new Exception("Order not found with ID: " . $order_id);
    }
    
    // Start transaction
    mysqli_begin_transaction($connection);
    
    $updates_made = false;
    
    // Update order status if provided
    if ($order_status !== null && $order_status !== '') {
        // Validate order_status
        $valid_statuses = ['Pending', 'Preparing', 'Ready', 'Complete', 'Cancelled', 'Bill Generated', 'Running'];
        if (!in_array($order_status, $valid_statuses)) {
            throw new Exception("Invalid order_status: '" . $order_status . "'. Allowed values: " . implode(', ', $valid_statuses));
        }
        
        $update_order_sql = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE order_id = ?";
        $update_order_stmt = mysqli_prepare($connection, $update_order_sql);
        
        if (!$update_order_stmt) {
            throw new Exception("Error preparing order update: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($update_order_stmt, "si", $order_status, $order_id);
        
        if (!mysqli_stmt_execute($update_order_stmt)) {
            throw new Exception("Error updating order status: " . mysqli_error($connection));
        }
        
        mysqli_stmt_close($update_order_stmt);
        $updates_made = true;
    }
    
    // Handle payment status update (this also updates bills table)
    // Re-check payment_status from input in case it wasn't captured properly
    if ($payment_status === null || $payment_status === '') {
        // Try all possible variations
        if (isset($input['payment_status']) && trim($input['payment_status']) !== '') {
            $payment_status = trim($input['payment_status']);
        } elseif (isset($input['paymentStatus']) && trim($input['paymentStatus']) !== '') {
            $payment_status = trim($input['paymentStatus']);
        } elseif (isset($_POST['payment_status']) && trim($_POST['payment_status']) !== '') {
            $payment_status = trim($_POST['payment_status']);
        } elseif (isset($_GET['payment_status']) && trim($_GET['payment_status']) !== '') {
            $payment_status = trim($_GET['payment_status']);
        }
    }
    
    // Log after re-check
    if ($payment_status) {
        error_log("Payment status found: " . $payment_status);
    }
    
    if ($payment_status !== null && $payment_status !== '') {
        // Find bill for this order
        $find_bill_sql = "SELECT b.*, o.customer_id 
                         FROM bills b 
                         LEFT JOIN orders o ON b.order_id = o.order_id 
                         WHERE b.order_id = ? 
                         ORDER BY b.bill_id DESC 
                         LIMIT 1";
        $find_bill_stmt = mysqli_prepare($connection, $find_bill_sql);
        
        if (!$find_bill_stmt) {
            throw new Exception("Error finding bill: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($find_bill_stmt, "i", $order_id);
        mysqli_stmt_execute($find_bill_stmt);
        $bill_result = mysqli_stmt_get_result($find_bill_stmt);
        $existing_bill = mysqli_fetch_assoc($bill_result);
        mysqli_stmt_close($find_bill_stmt);
        
        if ($existing_bill) {
            // Store old bill data for customer balance calculation
            $old_payment_method = $existing_bill['payment_method'] ?? 'Cash';
            $old_payment_status = $existing_bill['payment_status'] ?? 'Unpaid';
            $old_grand_total = floatval($existing_bill['grand_total'] ?? 0);
            $old_customer_id = isset($existing_bill['customer_id']) && $existing_bill['customer_id'] > 0 ? intval($existing_bill['customer_id']) : null;
            
            // Normalize payment_method
            $new_payment_method = $payment_method;
            if (!$new_payment_method) {
                $new_payment_method = $old_payment_method;
            }
            
            $payment_method_lower = strtolower(trim($new_payment_method));
            if (in_array($payment_method_lower, ['credit', 'cred'])) {
                $new_payment_method = 'Credit';
            } elseif (in_array($payment_method_lower, ['card', 'debit', 'credit card'])) {
                $new_payment_method = 'Card';
            } elseif (in_array($payment_method_lower, ['online', 'upi', 'digital', 'netbanking'])) {
                $new_payment_method = 'Online';
            } elseif (in_array($payment_method_lower, ['cash'])) {
                $new_payment_method = 'Cash';
            }
            
            // Validate payment_status
            if (!in_array($payment_status, ['Paid', 'Unpaid', 'Credit'])) {
                $payment_status = 'Unpaid';
            }
            
            // Update bill payment status and method
            $update_bill_sql = "UPDATE bills SET payment_status = ?, payment_method = ?, updated_at = NOW() WHERE bill_id = ?";
            $update_bill_stmt = mysqli_prepare($connection, $update_bill_sql);
            
            if (!$update_bill_stmt) {
                throw new Exception("Error preparing bill update: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($update_bill_stmt, "ssi", $payment_status, $new_payment_method, $existing_bill['bill_id']);
            
            if (!mysqli_stmt_execute($update_bill_stmt)) {
                throw new Exception("Error updating bill: " . mysqli_error($connection));
            }
            
            mysqli_stmt_close($update_bill_stmt);
            $updates_made = true;
            
            // Handle customer balance update (using the same logic from bills_management.php)
            $customer_id = $old_customer_id;
            if ($customer_id) {
                // Helper function to check if bill is credit
                $old_was_credit = false;
                $old_payment_method_lower = strtolower(trim($old_payment_method));
                if (in_array($old_payment_method_lower, ['credit', 'cred']) || 
                    strtolower($old_payment_status) === 'credit' ||
                    (strtolower($old_payment_status) === 'unpaid' && $old_customer_id > 0)) {
                    $old_was_credit = true;
                }
                
                $new_is_credit = false;
                $new_payment_method_lower = strtolower(trim($new_payment_method));
                if (in_array($new_payment_method_lower, ['credit', 'cred']) || 
                    strtolower($payment_status) === 'credit' ||
                    (strtolower($payment_status) === 'unpaid' && $customer_id > 0)) {
                    $new_is_credit = true;
                }
                
                // Update customer balance
                if ($old_was_credit && !$new_is_credit && $old_grand_total > 0) {
                    // Credit bill changed to non-credit (e.g., paid) - subtract old amount
                    $update_customer_sql = "UPDATE customers 
                                            SET balance = GREATEST(COALESCE(balance, 0) - ?, 0),
                                                updated_at = NOW()
                                            WHERE customer_id = ?";
                    $update_customer_stmt = mysqli_prepare($connection, $update_customer_sql);
                    if ($update_customer_stmt) {
                        mysqli_stmt_bind_param($update_customer_stmt, "di", $old_grand_total, $customer_id);
                        mysqli_stmt_execute($update_customer_stmt);
                        mysqli_stmt_close($update_customer_stmt);
                    }
                } elseif (!$old_was_credit && $new_is_credit && $old_grand_total > 0) {
                    // Non-credit bill changed to credit - add new amount
                    $update_customer_sql = "UPDATE customers 
                                            SET balance = COALESCE(balance, 0) + ?,
                                                updated_at = NOW()
                                            WHERE customer_id = ?";
                    $update_customer_stmt = mysqli_prepare($connection, $update_customer_sql);
                    if ($update_customer_stmt) {
                        mysqli_stmt_bind_param($update_customer_stmt, "di", $old_grand_total, $customer_id);
                        mysqli_stmt_execute($update_customer_stmt);
                        mysqli_stmt_close($update_customer_stmt);
                    }
                }
            }
            
            // If payment_status is "Paid", update order status to "Complete"
            if ($payment_status === 'Paid') {
                $update_order_complete_sql = "UPDATE orders SET 
                                            order_status = 'Complete',
                                            payment_mode = ?,
                                            updated_at = NOW()
                                            WHERE order_id = ?";
                $update_order_complete_stmt = mysqli_prepare($connection, $update_order_complete_sql);
                
                if ($update_order_complete_stmt) {
                    mysqli_stmt_bind_param($update_order_complete_stmt, "si", $new_payment_method, $order_id);
                    mysqli_stmt_execute($update_order_complete_stmt);
                    mysqli_stmt_close($update_order_complete_stmt);
                    $updates_made = true;
                }
            } elseif (strtolower($new_payment_method) === 'credit') {
                // For Credit payments, ensure order status is "Bill Generated"
                $update_order_credit_sql = "UPDATE orders SET 
                                          order_status = 'Bill Generated',
                                          payment_mode = ?,
                                          updated_at = NOW()
                                          WHERE order_id = ?";
                $update_order_credit_stmt = mysqli_prepare($connection, $update_order_credit_sql);
                
                if ($update_order_credit_stmt) {
                    mysqli_stmt_bind_param($update_order_credit_stmt, "si", $new_payment_method, $order_id);
                    mysqli_stmt_execute($update_order_credit_stmt);
                    mysqli_stmt_close($update_order_credit_stmt);
                    $updates_made = true;
                }
            }
        } else {
            // No bill found - just update order if payment_status is provided
            if ($payment_status === 'Paid') {
                $update_order_complete_sql = "UPDATE orders SET 
                                            order_status = 'Complete',
                                            updated_at = NOW()";
                if ($payment_method) {
                    $update_order_complete_sql .= ", payment_mode = ?";
                }
                $update_order_complete_sql .= " WHERE order_id = ?";
                
                $update_order_complete_stmt = mysqli_prepare($connection, $update_order_complete_sql);
                
                if ($update_order_complete_stmt) {
                    if ($payment_method) {
                        mysqli_stmt_bind_param($update_order_complete_stmt, "si", $payment_method, $order_id);
                    } else {
                        mysqli_stmt_bind_param($update_order_complete_stmt, "i", $order_id);
                    }
                    mysqli_stmt_execute($update_order_complete_stmt);
                    mysqli_stmt_close($update_order_complete_stmt);
                    $updates_made = true;
                }
            }
        }
    }
    
    // Update payment_mode in orders if provided (without payment_status)
    if ($payment_method !== null && $payment_method !== '' && $payment_status === null && $order_status === null) {
        // Normalize payment_method
        $payment_method_lower = strtolower(trim($payment_method));
        if (in_array($payment_method_lower, ['credit', 'cred'])) {
            $payment_method = 'Credit';
        } elseif (in_array($payment_method_lower, ['card', 'debit', 'credit card'])) {
            $payment_method = 'Card';
        } elseif (in_array($payment_method_lower, ['online', 'upi', 'digital', 'netbanking'])) {
            $payment_method = 'Online';
        } elseif (in_array($payment_method_lower, ['cash'])) {
            $payment_method = 'Cash';
        }
        
        $update_payment_mode_sql = "UPDATE orders SET payment_mode = ?, updated_at = NOW() WHERE order_id = ?";
        $update_payment_mode_stmt = mysqli_prepare($connection, $update_payment_mode_sql);
        
        if ($update_payment_mode_stmt) {
            mysqli_stmt_bind_param($update_payment_mode_stmt, "si", $payment_method, $order_id);
            mysqli_stmt_execute($update_payment_mode_stmt);
            mysqli_stmt_close($update_payment_mode_stmt);
            $updates_made = true;
        }
    }
    
    // If no updates were made yet, try to process payment_status if it exists in input
    // This handles cases where payment_status might not have been captured in initial extraction
    if (!$updates_made) {
        // Re-check all possible payment_status field names
        $payment_status_found = null;
        if (isset($input['payment_status']) && trim($input['payment_status']) !== '') {
            $payment_status_found = trim($input['payment_status']);
        } elseif (isset($input['paymentStatus']) && trim($input['paymentStatus']) !== '') {
            $payment_status_found = trim($input['paymentStatus']);
        }
        
        if ($payment_status_found) {
            // Find bill and update it
            $find_bill_sql = "SELECT b.*, o.customer_id 
                             FROM bills b 
                             LEFT JOIN orders o ON b.order_id = o.order_id 
                             WHERE b.order_id = ? 
                             ORDER BY b.bill_id DESC 
                             LIMIT 1";
            $find_bill_stmt = mysqli_prepare($connection, $find_bill_sql);
            
            if ($find_bill_stmt) {
                mysqli_stmt_bind_param($find_bill_stmt, "i", $order_id);
                mysqli_stmt_execute($find_bill_stmt);
                $bill_result = mysqli_stmt_get_result($find_bill_stmt);
                $bill_data = mysqli_fetch_assoc($bill_result);
                mysqli_stmt_close($find_bill_stmt);
                
                if ($bill_data) {
                    // Get payment_method if available
                    $pm = $payment_method;
                    if (!$pm && isset($bill_data['payment_method'])) {
                        $pm = $bill_data['payment_method'];
                    }
                    if (!$pm) {
                        $pm = 'Cash';
                    }
                    
                    // Normalize payment_method
                    $pm_lower = strtolower(trim($pm));
                    if (in_array($pm_lower, ['credit', 'cred'])) {
                        $pm = 'Credit';
                    } elseif (in_array($pm_lower, ['card', 'debit', 'credit card'])) {
                        $pm = 'Card';
                    } elseif (in_array($pm_lower, ['online', 'upi', 'digital', 'netbanking'])) {
                        $pm = 'Online';
                    } elseif (in_array($pm_lower, ['cash'])) {
                        $pm = 'Cash';
                    }
                    
                    // Store old bill data for customer balance calculation
                    $old_payment_method = $bill_data['payment_method'] ?? 'Cash';
                    $old_payment_status = $bill_data['payment_status'] ?? 'Unpaid';
                    $old_grand_total = floatval($bill_data['grand_total'] ?? 0);
                    $old_customer_id = isset($bill_data['customer_id']) && $bill_data['customer_id'] > 0 ? intval($bill_data['customer_id']) : null;
                    
                    // Update bill payment status
                    $update_bill_sql = "UPDATE bills SET payment_status = ?, payment_method = ?, updated_at = NOW() WHERE bill_id = ?";
                    $update_bill_stmt = mysqli_prepare($connection, $update_bill_sql);
                    
                    if ($update_bill_stmt) {
                        mysqli_stmt_bind_param($update_bill_stmt, "ssi", $payment_status_found, $pm, $bill_data['bill_id']);
                        mysqli_stmt_execute($update_bill_stmt);
                        mysqli_stmt_close($update_bill_stmt);
                        $updates_made = true;
                    }
                    
                    // Handle customer balance update
                    if ($old_customer_id) {
                        // Check if old was credit
                        $old_was_credit = false;
                        $old_pm_lower = strtolower(trim($old_payment_method));
                        if (in_array($old_pm_lower, ['credit', 'cred']) || 
                            strtolower($old_payment_status) === 'credit' ||
                            (strtolower($old_payment_status) === 'unpaid' && $old_customer_id > 0)) {
                            $old_was_credit = true;
                        }
                        
                        // Check if new is credit
                        $new_is_credit = false;
                        $new_pm_lower = strtolower(trim($pm));
                        if (in_array($new_pm_lower, ['credit', 'cred']) || 
                            strtolower($payment_status_found) === 'credit' ||
                            (strtolower($payment_status_found) === 'unpaid' && $old_customer_id > 0)) {
                            $new_is_credit = true;
                        }
                        
                        // Update customer balance
                        if ($old_was_credit && !$new_is_credit && $old_grand_total > 0) {
                            // Credit bill changed to non-credit (e.g., paid) - subtract old amount
                            $update_customer_sql = "UPDATE customers 
                                                    SET balance = GREATEST(COALESCE(balance, 0) - ?, 0),
                                                        updated_at = NOW()
                                                    WHERE customer_id = ?";
                            $update_customer_stmt = mysqli_prepare($connection, $update_customer_sql);
                            if ($update_customer_stmt) {
                                mysqli_stmt_bind_param($update_customer_stmt, "di", $old_grand_total, $old_customer_id);
                                mysqli_stmt_execute($update_customer_stmt);
                                mysqli_stmt_close($update_customer_stmt);
                            }
                        } elseif (!$old_was_credit && $new_is_credit && $old_grand_total > 0) {
                            // Non-credit bill changed to credit - add new amount
                            $update_customer_sql = "UPDATE customers 
                                                    SET balance = COALESCE(balance, 0) + ?,
                                                        updated_at = NOW()
                                                    WHERE customer_id = ?";
                            $update_customer_stmt = mysqli_prepare($connection, $update_customer_sql);
                            if ($update_customer_stmt) {
                                mysqli_stmt_bind_param($update_customer_stmt, "di", $old_grand_total, $old_customer_id);
                                mysqli_stmt_execute($update_customer_stmt);
                                mysqli_stmt_close($update_customer_stmt);
                            }
                        }
                    }
                    
                    // If payment_status is "Paid", update order status to Complete
                    if ($payment_status_found === 'Paid') {
                        $update_order_complete_sql = "UPDATE orders SET order_status = 'Complete', payment_mode = ?, updated_at = NOW() WHERE order_id = ?";
                        $update_order_complete_stmt = mysqli_prepare($connection, $update_order_complete_sql);
                        
                        if ($update_order_complete_stmt) {
                            mysqli_stmt_bind_param($update_order_complete_stmt, "si", $pm, $order_id);
                            mysqli_stmt_execute($update_order_complete_stmt);
                            mysqli_stmt_close($update_order_complete_stmt);
                            $updates_made = true;
                        }
                    }
                }
            }
        }
    }
    
    if (!$updates_made) {
        // Log what was received for debugging
        error_log("No updates made - Received: order_id=$order_id, order_status=" . ($order_status ?? 'null') . ", payment_status=" . ($payment_status ?? 'null') . ", payment_method=" . ($payment_method ?? 'null'));
        error_log("Full input received: " . json_encode($input));
        error_log("POST data: " . json_encode($_POST));
        error_log("GET data: " . json_encode($_GET));
        error_log("Raw input: " . $raw_input);
        
        // Return a more helpful error message
        $error_details = [
            "order_id" => $order_id,
            "order_status" => $order_status ?? "not provided",
            "payment_status" => $payment_status ?? "not provided",
            "payment_method" => $payment_method ?? "not provided",
            "received_input" => $input
        ];
        
        throw new Exception("No valid updates provided. Please provide at least one of: order_status, payment_status, or payment_method. " . json_encode($error_details));
    }
    
    // Commit transaction
    if (!mysqli_commit($connection)) {
        throw new Exception("Error committing transaction: " . mysqli_error($connection));
    }
    
    // Get updated order data for response
    $get_updated_order_sql = "SELECT order_id, order_status, payment_mode FROM orders WHERE order_id = ?";
    $get_updated_order_stmt = mysqli_prepare($connection, $get_updated_order_sql);
    $updated_order_data = null;
    if ($get_updated_order_stmt) {
        mysqli_stmt_bind_param($get_updated_order_stmt, "i", $order_id);
        mysqli_stmt_execute($get_updated_order_stmt);
        $updated_result = mysqli_stmt_get_result($get_updated_order_stmt);
        $updated_order_data = mysqli_fetch_assoc($updated_result);
        mysqli_stmt_close($get_updated_order_stmt);
    }
    
    // Get updated bill data for response
    $get_updated_bill_sql = "SELECT payment_status, payment_method FROM bills WHERE order_id = ? ORDER BY bill_id DESC LIMIT 1";
    $get_updated_bill_stmt = mysqli_prepare($connection, $get_updated_bill_sql);
    $updated_bill_data = null;
    if ($get_updated_bill_stmt) {
        mysqli_stmt_bind_param($get_updated_bill_stmt, "i", $order_id);
        mysqli_stmt_execute($get_updated_bill_stmt);
        $updated_bill_result = mysqli_stmt_get_result($get_updated_bill_stmt);
        $updated_bill_data = mysqli_fetch_assoc($updated_bill_result);
        mysqli_stmt_close($get_updated_bill_stmt);
    }
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    $response_data = [
        "success" => true,
        "message" => "Order status updated successfully",
        "data" => [
            "order_id" => $order_id,
            "order_status" => $updated_order_data['order_status'] ?? $order_status ?? $existing_order['order_status'] ?? null,
            "payment_status" => $updated_bill_data['payment_status'] ?? $payment_status ?? null,
            "payment_method" => $updated_bill_data['payment_method'] ?? $updated_order_data['payment_mode'] ?? $payment_method ?? null
        ]
    ];
    
    error_log("Change Order Status - Success response: " . json_encode($response_data));
    
    echo json_encode($response_data);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (mysqli_get_server_info($connection)) {
        mysqli_rollback($connection);
    }
    
    error_log("Change Order Status Error: " . $e->getMessage());
    
    // Clear buffer and output JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
}

