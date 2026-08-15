<?php

/**
 * Dishes Management API
 * CRUD operations for menu items (dishes)
 * Supports multi-branch dish management
 * 
 * Branch-Admin: Automatically uses their branch_id (must be provided in request)
 * Super-Admin: Can specify branch_id for any branch
 * 
 * POST Parameters (JSON):
 * - dish_id (int, optional) - Required for update, empty for create
 * - category_id (int, optional) - Category ID
 * - name (string, required) - Dish name
 * - description (string, optional) - Dish description
 * - price (float, required) - Dish price (must be > 0)
 * - qnty (string, optional) - Quantity (default: '1')
 * - barcode (string, optional) - Barcode
 * - is_available (int, optional) - Available status (default: 1)
 * - is_frequent (int, optional) - Frequent status (default: 1)
 * - discount (float, optional) - Discount (default: 0)
 * - kitchen_id (int, optional) - Kitchen ID
 * - terminal (int, required) - Terminal number
 * - branch_id (int/string, required) - Branch ID (MUST be provided)
 * 
 * DELETE Parameters (JSON):
 * - dish_id (int, required)
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
    
    if ($method === 'POST') {
        // CREATE or UPDATE
        $dish_id = isset($input['dish_id']) ? trim($input['dish_id']) : '';
        $category_id = isset($input['category_id']) && $input['category_id'] ? intval($input['category_id']) : null;
        $name = isset($input['name']) ? trim($input['name']) : '';
        $description = isset($input['description']) ? trim($input['description']) : '';
        $price = isset($input['price']) ? floatval($input['price']) : 0;
        $is_available = isset($input['is_available']) ? intval($input['is_available']) : 1;
        $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
        $branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : null;
        $discount = isset($input['discount']) ? floatval($input['discount']) : 0;
        $kitchen_id = isset($input['kitchen_id']) && $input['kitchen_id'] ? intval($input['kitchen_id']) : null;
        $qnty = isset($input['qnty']) ? $input['qnty'] : '1';
        $barcode = isset($input['barcode']) ? trim($input['barcode']) : '';
        $is_frequent = isset($input['is_frequent']) ? intval($input['is_frequent']) : 1;
        
        // Validate branch_id
        if (!$branch_id_input || $branch_id_input === '' || $branch_id_input === 'null' || $branch_id_input === 'undefined') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'branch_id is required',
                'message' => 'Branch ID must be provided'
            ]);
            exit();
        }
        
        $branch_id = intval($branch_id_input);
        if ($branch_id <= 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid branch_id',
                'message' => 'Branch ID must be a valid positive integer'
            ]);
            exit();
        }
        
        // Validate required fields
        if (empty($name)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'name is required',
                'message' => 'Dish name is required'
            ]);
            exit();
        }
        
        if ($price <= 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'price is required',
                'message' => 'Price must be greater than 0'
            ]);
            exit();
        }
        
        // Validate category_id exists and belongs to the same branch
        if ($category_id) {
            $category_check_sql = "SELECT category_id FROM categories 
                                  WHERE category_id = ? AND branch_id = ? AND terminal = ?";
            $category_check_stmt = mysqli_prepare($connection, $category_check_sql);
            if (!$category_check_stmt) {
                throw new Exception("Error preparing category check statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($category_check_stmt, "iii", $category_id, $branch_id, $terminal);
            mysqli_stmt_execute($category_check_stmt);
            $category_check_result = mysqli_stmt_get_result($category_check_stmt);
            
            if (mysqli_num_rows($category_check_result) === 0) {
                mysqli_stmt_close($category_check_stmt);
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Content-Type: application/json; charset=UTF-8");
                }
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid category',
                    'message' => 'Category does not exist or does not belong to this branch'
                ]);
                exit();
            }
            mysqli_stmt_close($category_check_stmt);
        }

    // Auto-generate barcode if not provided
    if (empty($barcode)) {
        $barcode = rand(10000000, 99999999); // Generate random 8-digit barcode
    }

    if (empty($dish_id)) {
            // CREATE NEW DISH
            // Check if dish name already exists for this branch
            $check_sql = "SELECT dish_id FROM dishes 
                         WHERE name = ? AND branch_id = ? AND terminal = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            if (!$check_stmt) {
                throw new Exception("Error preparing check statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($check_stmt, "sii", $name, $branch_id, $terminal);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                mysqli_stmt_close($check_stmt);
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Content-Type: application/json; charset=UTF-8");
                }
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Dish already exists',
                    'message' => 'A dish with this name already exists for this branch'
                ]);
                exit();
            }
            mysqli_stmt_close($check_stmt);
            
            // Insert new dish (no kitchen_id - kitchen comes from category)
            $insert_sql = "INSERT INTO dishes 
                          (name, description, price, qnty, barcode, is_available, is_frequent, 
                           discount, category_id, terminal, branch_id, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $insert_stmt = mysqli_prepare($connection, $insert_sql);
            if (!$insert_stmt) {
                throw new Exception("Error preparing insert statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($insert_stmt, "ssdssiidiii", 
                $name, $description, $price, $qnty, $barcode, 
                $is_available, $is_frequent, $discount, $category_id, $terminal, $branch_id);
            
            if (!mysqli_stmt_execute($insert_stmt)) {
                $error_msg = mysqli_error($connection);
                mysqli_stmt_close($insert_stmt);
                throw new Exception('Failed to create dish: ' . $error_msg);
            }
            
            $new_dish_id = mysqli_insert_id($connection);
            mysqli_stmt_close($insert_stmt);
            
            // Fetch the created dish with branch and category info (kitchen from category)
            $fetch_sql = "SELECT 
                            d.dish_id,
                            d.name,
                            d.description,
                            d.price,
                            d.qnty,
                            d.barcode,
                            d.is_available,
                            d.is_frequent,
                            d.discount,
                            d.category_id,
                            d.terminal,
                            d.branch_id,
                            c.name AS catname,
                            c.kitchen_id,
                            b.branch_name,
                            k.title AS kitchen_name
                        FROM dishes d
                        LEFT JOIN categories c ON d.category_id = c.category_id AND d.branch_id = c.branch_id AND d.terminal = c.terminal
                        LEFT JOIN branches b ON d.branch_id = b.branch_id
                        LEFT JOIN kitchens k ON c.kitchen_id = k.kitchen_id AND c.branch_id = k.branch_id AND c.terminal = k.terminal
                        WHERE d.dish_id = ?";
            $fetch_stmt = mysqli_prepare($connection, $fetch_sql);
            if (!$fetch_stmt) {
                throw new Exception("Error preparing fetch statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($fetch_stmt, "i", $new_dish_id);
            mysqli_stmt_execute($fetch_stmt);
            $fetch_result = mysqli_stmt_get_result($fetch_stmt);
            $dish = mysqli_fetch_assoc($fetch_result);
            
            $branch_name = $dish['branch_name'] ?? null;
            if (!$branch_name && $dish['branch_id']) {
                $branch_name = 'Branch ' . $dish['branch_id'];
            }
            
            $category_name = $dish['catname'] ?? null;
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Menu item created successfully',
                'data' => [
                    'dish_id' => intval($dish['dish_id']),
                    'name' => $dish['name'],
                    'description' => $dish['description'] ?? '',
                    'price' => floatval($dish['price']),
                    'qnty' => $dish['qnty'],
                    'barcode' => $dish['barcode'] ?? '',
                    'is_available' => intval($dish['is_available']),
                    'is_frequent' => intval($dish['is_frequent']),
                    'discount' => floatval($dish['discount']),
                    'category_id' => $dish['category_id'] ? intval($dish['category_id']) : null,
                    'category_name' => $category_name,
                    'kitchen_id' => isset($dish['kitchen_id']) && $dish['kitchen_id'] ? intval($dish['kitchen_id']) : null,
                    'kitchen_name' => $dish['kitchen_name'] ?? null,
                    'branch_id' => intval($dish['branch_id']),
                    'branch_name' => $branch_name,
                    'terminal' => intval($dish['terminal'])
                ]
            ]);
            mysqli_stmt_close($fetch_stmt);
            
        } else {
            // UPDATE EXISTING DISH
            $dish_id = intval($dish_id);
            
            // Check if dish exists
            $check_sql = "SELECT dish_id, branch_id FROM dishes WHERE dish_id = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            if (!$check_stmt) {
                throw new Exception("Error preparing check statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($check_stmt, "i", $dish_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                mysqli_stmt_close($check_stmt);
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Content-Type: application/json; charset=UTF-8");
                }
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Dish not found',
                    'message' => 'Dish with ID ' . $dish_id . ' does not exist'
                ]);
                exit();
            }
            
            $existing = mysqli_fetch_assoc($check_result);
            // Use existing branch_id if branch_id wasn't provided in update
            // This prevents branch-admin from changing branch_id of their dishes
            $update_branch_id = $branch_id ? $branch_id : intval($existing['branch_id']);
            mysqli_stmt_close($check_stmt);
            
            // Check if name already exists for another dish in the same branch
            $name_check_sql = "SELECT dish_id FROM dishes 
                              WHERE name = ? AND branch_id = ? AND terminal = ? AND dish_id != ?";
            $name_check_stmt = mysqli_prepare($connection, $name_check_sql);
            if (!$name_check_stmt) {
                throw new Exception("Error preparing name check statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($name_check_stmt, "siii", $name, $update_branch_id, $terminal, $dish_id);
            mysqli_stmt_execute($name_check_stmt);
            $name_check_result = mysqli_stmt_get_result($name_check_stmt);
            
            if (mysqli_num_rows($name_check_result) > 0) {
                mysqli_stmt_close($name_check_stmt);
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header("Content-Type: application/json; charset=UTF-8");
                }
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Dish name already exists',
                    'message' => 'A dish with this name already exists for this branch'
                ]);
                exit();
            }
            mysqli_stmt_close($name_check_stmt);
            
            // Update dish (no kitchen_id - kitchen comes from category)
            $update_sql = "UPDATE dishes 
                          SET name = ?, 
                              description = ?, 
                              price = ?,
                              qnty = ?,
                              barcode = ?,
                              is_available = ?,
                              is_frequent = ?,
                              discount = ?,
                              category_id = ?,
                              branch_id = ?,
                              updated_at = NOW()
                          WHERE dish_id = ? AND terminal = ?";
            $update_stmt = mysqli_prepare($connection, $update_sql);
            if (!$update_stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($connection));
            }
            
            // Type string: s=name, s=description, d=price, s=qnty, s=barcode, i=is_available, i=is_frequent, d=discount, i=category_id, i=branch_id, i=dish_id, i=terminal (12 params)
            mysqli_stmt_bind_param($update_stmt, "ssdssiidiiii", 
                $name, $description, $price, $qnty, $barcode, 
                $is_available, $is_frequent, $discount, $category_id, 
                $update_branch_id, $dish_id, $terminal);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                $error_msg = mysqli_error($connection);
                mysqli_stmt_close($update_stmt);
                throw new Exception('Failed to update dish: ' . $error_msg);
            }
            
            mysqli_stmt_close($update_stmt);
            
            // Fetch updated dish with branch and category info (kitchen from category)
            $fetch_sql = "SELECT 
                            d.dish_id,
                            d.name,
                            d.description,
                            d.price,
                            d.qnty,
                            d.barcode,
                            d.is_available,
                            d.is_frequent,
                            d.discount,
                            d.category_id,
                            d.terminal,
                            d.branch_id,
                            c.name AS catname,
                            c.kitchen_id,
                            b.branch_name,
                            k.title AS kitchen_name
                        FROM dishes d
                        LEFT JOIN categories c ON d.category_id = c.category_id AND d.branch_id = c.branch_id AND d.terminal = c.terminal
                        LEFT JOIN branches b ON d.branch_id = b.branch_id
                        LEFT JOIN kitchens k ON c.kitchen_id = k.kitchen_id AND c.branch_id = k.branch_id AND c.terminal = k.terminal
                        WHERE d.dish_id = ?";
            $fetch_stmt = mysqli_prepare($connection, $fetch_sql);
            if (!$fetch_stmt) {
                throw new Exception("Error preparing fetch statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($fetch_stmt, "i", $dish_id);
            mysqli_stmt_execute($fetch_stmt);
            $fetch_result = mysqli_stmt_get_result($fetch_stmt);
            $dish = mysqli_fetch_assoc($fetch_result);
            
            $branch_name = $dish['branch_name'] ?? null;
            if (!$branch_name && $dish['branch_id']) {
                $branch_name = 'Branch ' . $dish['branch_id'];
            }
            
            $category_name = $dish['catname'] ?? null;
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Menu item updated successfully',
                'data' => [
                    'dish_id' => intval($dish['dish_id']),
                    'name' => $dish['name'],
                    'description' => $dish['description'] ?? '',
                    'price' => floatval($dish['price']),
                    'qnty' => $dish['qnty'],
                    'barcode' => $dish['barcode'] ?? '',
                    'is_available' => intval($dish['is_available']),
                    'is_frequent' => intval($dish['is_frequent']),
                    'discount' => floatval($dish['discount']),
                    'category_id' => $dish['category_id'] ? intval($dish['category_id']) : null,
                    'category_name' => $category_name,
                    'kitchen_id' => isset($dish['kitchen_id']) && $dish['kitchen_id'] ? intval($dish['kitchen_id']) : null,
                    'kitchen_name' => $dish['kitchen_name'] ?? null,
                    'branch_id' => intval($dish['branch_id']),
                    'branch_name' => $branch_name,
                    'terminal' => intval($dish['terminal'])
                ]
            ]);
            mysqli_stmt_close($fetch_stmt);
        }
        
    } elseif ($method === 'DELETE') {
        // DELETE DISH
        $dish_id = isset($input['dish_id']) ? intval($input['dish_id']) : 0;
        
        if ($dish_id <= 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid dish_id',
                'message' => 'Dish ID is required'
            ]);
            exit();
        }
        
        // Check if dish exists
        $check_sql = "SELECT dish_id FROM dishes WHERE dish_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        if (!$check_stmt) {
            throw new Exception("Error preparing check statement: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param($check_stmt, "i", $dish_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) === 0) {
            mysqli_stmt_close($check_stmt);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Dish not found',
                'message' => 'Dish does not exist'
            ]);
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        // Delete dish
        $delete_sql = "DELETE FROM dishes WHERE dish_id = ?";
        $delete_stmt = mysqli_prepare($connection, $delete_sql);
        if (!$delete_stmt) {
            throw new Exception("Error preparing delete statement: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param($delete_stmt, "i", $dish_id);
        
        if (!mysqli_stmt_execute($delete_stmt)) {
            $error_msg = mysqli_error($connection);
            mysqli_stmt_close($delete_stmt);
            throw new Exception('Failed to delete dish: ' . $error_msg);
        }
        
        mysqli_stmt_close($delete_stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Menu item deleted successfully'
        ]);
        
    } else {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed',
            'message' => 'Only POST and DELETE methods are allowed'
        ]);
        exit();
    }
    
} catch (Exception $e) {
    error_log("Dishes Management Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
    exit();
}

exit();
?>
