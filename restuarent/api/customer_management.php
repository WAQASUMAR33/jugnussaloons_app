<?php
/**
 * Customer Management API
 * CRUD operations for customers
 * Supports multi-branch customer management
 * 
 * POST Parameters (JSON):
 * - id (int, optional) - Customer ID for update, empty for create
 * - customer_id (int, optional) - Alternative to id
 * - name (string, required) - Customer name
 * - phone (string, optional) - Phone number (also accepts mobileNo for backward compatibility)
 * - email (string, optional) - Email address
 * - address (string, optional) - Address
 * - balance (float, optional) - Credit balance (default: 0)
 * 
 * DELETE Parameters (JSON):
 * - id (int, required) - Customer ID to delete
 * - customer_id (int, optional) - Alternative to id
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

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Handle OPTIONS request for CORS
    if ($method === 'OPTIONS') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        exit();
    }
    
    // Get input data - handle both JSON and form data
    $input = [];
    $raw_input = file_get_contents('php://input');
    
    // For POST and DELETE requests, try JSON body first
    if (($method === 'POST' || $method === 'DELETE') && $raw_input) {
        $json_input = json_decode($raw_input, true);
        if ($json_input && is_array($json_input)) {
            $input = $json_input;
        }
    }
    
    // Fallback to POST form data
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    // For GET requests, use query parameters
    if ($method === 'GET' && empty($input)) {
        if (!empty($_GET)) {
            $input = $_GET;
        }
    }
    
    // Actual table structure uses customer_id as primary key
    $id_column = 'customer_id';
    
    if ($method === 'GET') {
        // GET: Fetch customers (all or by ID) with credit statistics
        // Actual table structure: customer_id, name, phone, email, address, balance, created_at, updated_at
        $id = isset($input["id"]) && $input["id"] !== '' ? trim($input["id"]) : (isset($input["customer_id"]) && $input["customer_id"] !== '' ? trim($input["customer_id"]) : '');
        $branch_id = isset($input["branch_id"]) && $input["branch_id"] !== '' ? intval($input["branch_id"]) : null;
        
        // Build SQL query with credit statistics
        // Credit detection logic:
        // 1. payment_method = 'Credit' OR
        // 2. payment_status = 'Credit' OR  
        // 3. (payment_status = 'Unpaid' AND customer_id IS NOT NULL)
        // Note: bills are linked to customers via orders table
        
        $whereConditions = [];
        $whereParams = [];
        $whereTypes = '';
        
        // If ID is provided, fetch single customer
        if (!empty($id)) {
            $id_int = intval($id);
            $whereConditions[] = "c.$id_column = ?";
            $whereParams[] = $id_int;
            $whereTypes .= 'i';
        }
        
        // Build the main query
        // Get branch_id from customers table first, then fallback to orders table
        // Check if customers table has branch_id column (check once, use for both SELECT and WHERE)
        $check_branch_column = mysqli_query($connection, "SHOW COLUMNS FROM customers LIKE 'branch_id'");
        $has_branch_column = ($check_branch_column && mysqli_num_rows($check_branch_column) > 0);
        
        $branch_id_select = "";
        if ($has_branch_column) {
            // Customers table has branch_id column - use COALESCE to prioritize customers.branch_id
            $branch_id_select = "COALESCE(
                        NULLIF(c.branch_id, 0),
                        (SELECT o2.branch_id 
                         FROM orders o2 
                         WHERE o2.customer_id = c.customer_id 
                           AND o2.branch_id IS NOT NULL
                           AND o2.branch_id > 0
                         ORDER BY o2.created_at DESC, o2.order_id DESC 
                         LIMIT 1)
                    ) as branch_id";
        } else {
            // Customers table doesn't have branch_id column - only use orders table
            $branch_id_select = "(SELECT o2.branch_id 
                     FROM orders o2 
                     WHERE o2.customer_id = c.customer_id 
                       AND o2.branch_id IS NOT NULL
                       AND o2.branch_id > 0
                     ORDER BY o2.created_at DESC, o2.order_id DESC 
                     LIMIT 1) as branch_id";
        }
        
        $sql = "SELECT 
                    c.customer_id,
                    c.name,
                    c.phone,
                    c.email,
                    c.address,
                    c.balance,
                    c.created_at,
                    c.updated_at,
                    $branch_id_select,
                    COUNT(DISTINCT CASE WHEN (
                        (b.payment_method = 'Credit' OR LOWER(b.payment_method) = 'cred')
                        OR b.payment_status = 'Credit'
                        OR (b.payment_status = 'Unpaid' AND o.customer_id IS NOT NULL AND o.customer_id > 0)
                    ) THEN b.bill_id ELSE NULL END) as credit_orders_count,
                    COALESCE(SUM(CASE WHEN (
                        (b.payment_method = 'Credit' OR LOWER(b.payment_method) = 'cred')
                        OR b.payment_status = 'Credit'
                        OR (b.payment_status = 'Unpaid' AND o.customer_id IS NOT NULL AND o.customer_id > 0)
                    ) THEN b.grand_total ELSE 0 END), 0) as total_credit_amount
                FROM customers c
                LEFT JOIN orders o ON c.customer_id = o.customer_id
                LEFT JOIN bills b ON o.order_id = b.order_id";
        
        // Filter by branch_id if provided
        // Check both customers table (if branch_id column exists) and orders table
        // Use the $has_branch_column variable already checked above
        if ($branch_id !== null) {
            if ($has_branch_column) {
                // Customers table has branch_id column - check both customers.branch_id and orders.branch_id
                $whereConditions[] = "(
                    c.branch_id = ? 
                    OR EXISTS (SELECT 1 FROM orders o_filter WHERE o_filter.customer_id = c.customer_id AND o_filter.branch_id = ?)
                )";
                $whereParams[] = $branch_id;
                $whereParams[] = $branch_id;
                $whereTypes .= 'ii';
            } else {
                // Customers table doesn't have branch_id column - only check orders table
                $whereConditions[] = "EXISTS (SELECT 1 FROM orders o_filter WHERE o_filter.customer_id = c.customer_id AND o_filter.branch_id = ?)";
                $whereParams[] = $branch_id;
                $whereTypes .= 'i';
            }
        }
        
        if (count($whereConditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        // GROUP BY all non-aggregated columns for MySQL strict mode compatibility
        $sql .= " GROUP BY c.customer_id";
        $sql .= " ORDER BY c.name ASC";
        
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            $prep_error = mysqli_error($connection);
            error_log("Customer GET - Error preparing statement: " . $prep_error . " SQL: " . $sql);
            throw new Exception("Error preparing statement: " . $prep_error);
        }
        
        if (!empty($whereParams)) {
            if (!mysqli_stmt_bind_param($stmt, $whereTypes, ...$whereParams)) {
                $bind_error = mysqli_error($connection);
                error_log("Customer GET - Error binding parameters: " . $bind_error);
                mysqli_stmt_close($stmt);
                throw new Exception("Error binding parameters: " . $bind_error);
            }
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($connection);
            $stmt_error = mysqli_stmt_error($stmt);
            error_log("Customer GET - Error executing query: " . ($stmt_error ?: $error ?: "Unknown error") . " SQL: " . $sql);
            mysqli_stmt_close($stmt);
            throw new Exception("Error executing query: " . ($stmt_error ?: $error ?: "Unknown error"));
        }
        
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result) {
            $error = mysqli_error($connection);
            error_log("Customer GET - Error getting result: " . $error);
            mysqli_stmt_close($stmt);
            throw new Exception("Error getting result: " . ($error ?: "Unknown error"));
        }
        
        $customers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $customer_id = intval($row['customer_id'] ?? 0);
            $balance = floatval($row['balance'] ?? 0);
            $credit_orders_count = intval($row['credit_orders_count'] ?? 0);
            $total_credit_amount = floatval($row['total_credit_amount'] ?? 0);
            
            // Get branch_id from query result (already retrieved from subquery)
            $customer_branch_id = null;
            if (isset($row['branch_id']) && $row['branch_id'] !== null) {
                $branch_val = $row['branch_id'];
                if ($branch_val !== '' && intval($branch_val) > 0) {
                    $customer_branch_id = intval($branch_val);
                }
            }
            
            $customers[] = [
                'id' => $customer_id,
                'customer_id' => $customer_id,
                'name' => $row['name'] ?? '',
                'phone' => $row['phone'] ?? '',
                'mobileNo' => $row['phone'] ?? '', // Alias for backward compatibility
                'email' => $row['email'] ?? '',
                'address' => $row['address'] ?? '',
                'credit_limit' => isset($row['credit_limit']) ? floatval($row['credit_limit']) : null,
                'balance' => $balance,
                'credit' => $balance, // Alias for credit
                'branch_id' => $customer_branch_id,
                'credit_orders_count' => $credit_orders_count,
                'total_credit_amount' => $total_credit_amount,
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
        
        // If fetching single customer by ID, return single object instead of array
        if (!empty($id)) {
            if (count($customers) > 0) {
                $response = json_encode([
                    "success" => true,
                    "data" => $customers[0],
                    "count" => 1
                ], JSON_UNESCAPED_UNICODE);
            } else {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Content-Type: application/json; charset=UTF-8");
                }
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Customer not found",
                    "data" => null,
                    "count" => 0
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }
        } else {
            $response = json_encode([
                "success" => true,
                "data" => $customers,
                "count" => count($customers)
            ], JSON_UNESCAPED_UNICODE);
        }
        
        if ($response === false) {
            throw new Exception('JSON encoding failed: ' . json_last_error_msg());
        }
        
        echo $response;
        exit();
        
    } elseif ($method === 'POST') {
        // POST: Create or Update customer
        // Actual table structure: customer_id, name, phone, email, address, balance, branch_id, created_at, updated_at
        $id = isset($input['id']) && $input['id'] !== '' ? trim($input['id']) : (isset($input['customer_id']) && $input['customer_id'] !== '' ? trim($input['customer_id']) : '');
        $name = isset($input['name']) ? trim($input['name']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : (isset($input['mobileNo']) ? trim($input['mobileNo']) : ''); // Support both phone and mobileNo
        $email = isset($input['email']) ? trim($input['email']) : '';
        $address = isset($input['address']) ? trim($input['address']) : '';
        $balance = isset($input['balance']) ? floatval($input['balance']) : 0;
        $branch_id = isset($input['branch_id']) && $input['branch_id'] !== '' ? (intval($input['branch_id']) > 0 ? intval($input['branch_id']) : null) : null;
        
        // Validate required fields
        if (empty($name)) {
            throw new Exception("Customer name is required");
        }
        
        if (empty($id)) {
            // CREATE: Insert new customer
            // Try to include branch_id in INSERT if column exists in customers table
            $sql = "INSERT INTO customers (name, phone, email, address, balance";
            $params = [$name, $phone, $email, $address, $balance];
            $types = "ssssd";
            
            // Add branch_id if provided
            if ($branch_id !== null && $branch_id > 0) {
                $sql .= ", branch_id";
                $params[] = $branch_id;
                $types .= "i";
            }
            
            $sql .= ", created_at, updated_at) VALUES (?, ?, ?, ?, ?";
            if ($branch_id !== null && $branch_id > 0) {
                $sql .= ", ?";
            }
            $sql .= ", NOW(), NOW())";
            
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                $prep_error = mysqli_error($connection);
                // If branch_id column doesn't exist, try without it
                if ($branch_id !== null && $branch_id > 0 && (stripos($prep_error, 'branch_id') !== false || stripos($prep_error, 'Unknown column') !== false)) {
                    error_log("Warning: branch_id column may not exist in customers table. Retrying without branch_id.");
                    // Retry without branch_id
                    $sql = "INSERT INTO customers (name, phone, email, address, balance, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = mysqli_prepare($connection, $sql);
                    $params = [$name, $phone, $email, $address, $balance];
                    $types = "ssssd";
                }
                
                if (!$stmt) {
                    throw new Exception("Error preparing INSERT statement. " . ($prep_error ?: "Please check database connection and table structure.") . " SQL: " . $sql);
                }
            }
            
            // Bind parameters
            $bind_result = mysqli_stmt_bind_param($stmt, $types, ...$params);
            
            if (!$bind_result) {
                $bind_error = mysqli_error($connection);
                mysqli_stmt_close($stmt);
                throw new Exception("Error binding parameters: " . ($bind_error ?: "Check parameter types and values"));
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_error($connection);
                $stmt_error = mysqli_stmt_error($stmt);
                
                // If branch_id column doesn't exist, retry without it
                if ($branch_id !== null && $branch_id > 0 && (stripos($stmt_error, 'branch_id') !== false || stripos($error, 'branch_id') !== false || stripos($stmt_error, 'Unknown column') !== false)) {
                    error_log("Warning: branch_id column may not exist in customers table. Retrying without branch_id.");
                    mysqli_stmt_close($stmt);
                    
                    $sql = "INSERT INTO customers (name, phone, email, address, balance, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = mysqli_prepare($connection, $sql);
                    $params = [$name, $phone, $email, $address, $balance];
                    $types = "ssssd";
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, $types, ...$params);
                        if (!mysqli_stmt_execute($stmt)) {
                            $error = mysqli_error($connection);
                            $stmt_error = mysqli_stmt_error($stmt);
                            mysqli_stmt_close($stmt);
                            throw new Exception("Error executing INSERT: " . ($stmt_error ?: $error ?: "Unknown database error") . ". Please check if all required columns exist in customers table.");
                        }
                    } else {
                        throw new Exception("Error preparing INSERT statement. " . mysqli_error($connection));
                    }
                } else {
                    mysqli_stmt_close($stmt);
                    throw new Exception("Error executing INSERT: " . ($stmt_error ?: $error ?: "Unknown database error") . ". Please check if all required columns exist in customers table.");
                }
            }
            
            $new_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt);
            
            // If branch_id was provided but not stored in customers table, create a placeholder order
            // This ensures branch_id is available when fetching the customer
            if ($branch_id !== null && $branch_id > 0) {
                // Verify if branch_id was stored in customers table
                $check_sql = "SELECT branch_id FROM customers WHERE customer_id = ?";
                $check_stmt = mysqli_prepare($connection, $check_sql);
                $branch_id_stored = false;
                
                if ($check_stmt) {
                    mysqli_stmt_bind_param($check_stmt, "i", $new_id);
                    if (mysqli_stmt_execute($check_stmt)) {
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        if ($check_row = mysqli_fetch_assoc($check_result)) {
                            // Check if branch_id column exists and has value
                            if (isset($check_row['branch_id'])) {
                                $branch_id_stored = ($check_row['branch_id'] !== null && intval($check_row['branch_id']) > 0);
                            }
                        }
                    }
                    mysqli_stmt_close($check_stmt);
                }
                
                // Create placeholder order only if branch_id wasn't stored in customers table
                if (!$branch_id_stored) {
                // Create a minimal order record to associate customer with branch
                // This order will be used to determine the customer's branch_id
                $order_sql = "INSERT INTO orders (
                                order_type, 
                                order_status,
                                hall_id,
                                table_id,
                                comments,
                                terminal,
                                order_taker_id,
                                branch_id,
                                customer_id,
                                g_total_amount,
                                service_charge,
                                discount_amount,
                                net_total_amount,
                                payment_mode,
                                created_at,
                                updated_at
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $order_stmt = mysqli_prepare($connection, $order_sql);
                
                if ($order_stmt) {
                    $order_type = 'Customer Registration';
                    $order_status = 'Customer Created';
                    $hall_id = 0;
                    $table_id = 0;
                    $comments = 'Customer registration order';
                    $terminal = 1;
                    $order_taker_id = 0;
                    $g_total = 0.00;
                    $service_charge = 0.00;
                    $discount_amount = 0.00;
                    $net_total = 0.00;
                    $payment_mode = 'N/A';
                    
                    mysqli_stmt_bind_param($order_stmt, "ssiisiiiidddds", 
                        $order_type,
                        $order_status,
                        $hall_id,
                        $table_id,
                        $comments,
                        $terminal,
                        $order_taker_id,
                        $branch_id,
                        $new_id,
                        $g_total,
                        $service_charge,
                        $discount_amount,
                        $net_total,
                        $payment_mode
                    );
                    
                    // Execute the order creation
                    if (mysqli_stmt_execute($order_stmt)) {
                        // Order created successfully - branch_id is now associated
                        error_log("Customer registration order created for customer_id: $new_id, branch_id: $branch_id");
                    } else {
                        // Log error but don't fail customer creation
                        error_log("Warning: Failed to create customer registration order for customer_id: $new_id - " . mysqli_stmt_error($order_stmt));
                    }
                    mysqli_stmt_close($order_stmt);
                } else {
                    error_log("Warning: Failed to prepare customer registration order statement for customer_id: $new_id");
                }
                } // End if (!$branch_id_stored)
            } // End if ($branch_id !== null && $branch_id > 0)
            
            // Fetch the created customer data to return in response
            $get_customer_sql = "SELECT 
                                    c.customer_id,
                                    c.name,
                                    c.phone,
                                    c.email,
                                    c.address,
                                    c.balance,
                                    c.created_at,
                                    c.updated_at,
                                    COALESCE(
                                        NULLIF(c.branch_id, 0),
                                        (SELECT o2.branch_id 
                                         FROM orders o2 
                                         WHERE o2.customer_id = c.customer_id 
                                           AND o2.branch_id IS NOT NULL
                                           AND o2.branch_id > 0
                                         ORDER BY o2.created_at DESC, o2.order_id DESC 
                                         LIMIT 1)
                                    ) as branch_id
                                FROM customers c
                                WHERE c.customer_id = ?";
            
            $get_customer_stmt = mysqli_prepare($connection, $get_customer_sql);
            $customer_data = null;
            
            if ($get_customer_stmt) {
                mysqli_stmt_bind_param($get_customer_stmt, "i", $new_id);
                mysqli_stmt_execute($get_customer_stmt);
                $customer_result = mysqli_stmt_get_result($get_customer_stmt);
                $customer_data = mysqli_fetch_assoc($customer_result);
                mysqli_stmt_close($get_customer_stmt);
            }
            
            // Determine branch_id for response
            // Priority: 1) From customer_data (already includes branch_id from customers table or orders), 2) Provided branch_id
            $final_branch_id = null;
            if ($customer_data && isset($customer_data['branch_id']) && $customer_data['branch_id'] !== null && intval($customer_data['branch_id']) > 0) {
                $final_branch_id = intval($customer_data['branch_id']);
            } elseif ($branch_id !== null && $branch_id > 0) {
                // Use the branch_id provided during creation as fallback
                $final_branch_id = $branch_id;
            }
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            // Build response data with branch_id
            $response_data = [
                "id" => $new_id,
                "customer_id" => $new_id
            ];
            
            if ($customer_data) {
                $response_data = [
                    "id" => intval($customer_data['customer_id']),
                    "customer_id" => intval($customer_data['customer_id']),
                    "name" => $customer_data['name'] ?? '',
                    "phone" => $customer_data['phone'] ?? '',
                    "email" => $customer_data['email'] ?? '',
                    "address" => $customer_data['address'] ?? '',
                    "balance" => floatval($customer_data['balance'] ?? 0),
                    "branch_id" => $final_branch_id,
                    "credit_orders_count" => 0,
                    "total_credit_amount" => 0.00,
                    "created_at" => $customer_data['created_at'] ?? null,
                    "updated_at" => $customer_data['updated_at'] ?? null
                ];
            } else {
                // Fallback if customer fetch failed
                $response_data = [
                    "id" => $new_id,
                    "customer_id" => $new_id,
                    "branch_id" => $final_branch_id
                ];
            }
            
            echo json_encode([
                "success" => true,
                "message" => "Customer created successfully",
                "data" => $response_data
            ], JSON_UNESCAPED_UNICODE);
            exit();
            
        } else {
            // UPDATE: Update existing customer
            $id_int = intval($id);
            
            // Check if customer exists
            $check_sql = "SELECT $id_column FROM customers WHERE $id_column = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            
            if (!$check_stmt) {
                throw new Exception("Error preparing check statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($check_stmt, "i", $id_int);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                mysqli_stmt_close($check_stmt);
                throw new Exception("Customer not found");
            }
            
            mysqli_stmt_close($check_stmt);
            
            // UPDATE: Update existing customer
            // Try to include branch_id in UPDATE if column exists
            $update_fields = ['name = ?', 'phone = ?', 'email = ?', 'address = ?', 'balance = ?', 'updated_at = NOW()'];
            $update_values = [$name, $phone, $email, $address, $balance];
            $types = 'ssssd';
            
            // Add branch_id if provided
            if ($branch_id !== null) {
                $update_fields[] = 'branch_id = ?';
                $update_values[] = $branch_id;
                $types .= 'i';
            }
            
            $update_values[] = $id_int;
            $types .= 'i';
            
            $sql = "UPDATE customers SET " . implode(', ', $update_fields) . " WHERE $id_column = ?";
            
            $stmt = mysqli_prepare($connection, $sql);
            
            if (!$stmt) {
                $prep_error = mysqli_error($connection);
                // If branch_id column doesn't exist, retry without it
                if ($branch_id !== null && (stripos($prep_error, 'branch_id') !== false || stripos($prep_error, 'Unknown column') !== false)) {
                    error_log("Warning: branch_id column may not exist in customers table. Retrying update without branch_id.");
                    $sql = "UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, balance = ?, updated_at = NOW() WHERE $id_column = ?";
                    $stmt = mysqli_prepare($connection, $sql);
                    $types = 'ssssdi';
                    $update_values = [$name, $phone, $email, $address, $balance, $id_int];
                }
                
                if (!$stmt) {
                    throw new Exception("Error preparing UPDATE statement: " . mysqli_error($connection));
                }
            }
            
            mysqli_stmt_bind_param($stmt, $types, ...$update_values);
            
            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_error($connection);
                $stmt_error = mysqli_stmt_error($stmt);
                
                // If branch_id column doesn't exist, retry without it
                if ($branch_id !== null && (stripos($stmt_error, 'branch_id') !== false || stripos($error, 'branch_id') !== false || stripos($stmt_error, 'Unknown column') !== false)) {
                    error_log("Warning: branch_id column may not exist in customers table. Retrying update without branch_id.");
                    mysqli_stmt_close($stmt);
                    
                    $sql = "UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, balance = ?, updated_at = NOW() WHERE $id_column = ?";
                    $stmt = mysqli_prepare($connection, $sql);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "ssssdi", $name, $phone, $email, $address, $balance, $id_int);
                        if (!mysqli_stmt_execute($stmt)) {
                            $error = mysqli_error($connection);
                            $stmt_error = mysqli_stmt_error($stmt);
                            mysqli_stmt_close($stmt);
                            throw new Exception("Error updating customer: " . ($stmt_error ?: $error ?: "Unknown error"));
                        }
                    } else {
                        throw new Exception("Error preparing UPDATE statement: " . mysqli_error($connection));
                    }
                } else {
                    mysqli_stmt_close($stmt);
                    throw new Exception("Error updating customer: " . ($stmt_error ?: $error ?: "Unknown error"));
                }
            }
            
            mysqli_stmt_close($stmt);
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            echo json_encode([
                "success" => true,
                "message" => "Customer updated successfully",
                "data" => [
                    "id" => $id_int,
                    "customer_id" => $id_int
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
    } elseif ($method === 'DELETE') {
        // DELETE: Delete customer
        $id = isset($input['id']) && $input['id'] !== '' ? trim($input['id']) : (isset($input['customer_id']) && $input['customer_id'] !== '' ? trim($input['customer_id']) : '');
        
        if (empty($id)) {
            throw new Exception("Customer ID is required for deletion");
        }
        
        $id_int = intval($id);
        
        // Check if customer exists
        $check_sql = "SELECT $id_column FROM customers WHERE $id_column = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        
        if (!$check_stmt) {
            throw new Exception("Error preparing check statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($check_stmt, "i", $id_int);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) === 0) {
            mysqli_stmt_close($check_stmt);
            throw new Exception("Customer not found");
        }
        
        mysqli_stmt_close($check_stmt);
        
        // Delete customer
        $sql = "DELETE FROM customers WHERE $id_column = ?";
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $id_int);
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($connection);
            mysqli_stmt_close($stmt);
            throw new Exception("Error deleting customer: " . ($error ?: "Unknown error"));
        }
        
        mysqli_stmt_close($stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            "success" => true,
            "message" => "Customer deleted successfully"
        ], JSON_UNESCAPED_UNICODE);
        exit();
        
    } else {
        throw new Exception("Method not allowed");
    }
    
} catch (Exception $e) {
    error_log("Customer Management Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Error $e) {
    error_log("Customer Management Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Fatal error: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

exit();
?>

