<?php

/**
 * Category Management API
 * CRUD operations for categories with kitchen assignment
 * Supports multi-branch category management
 * 
 * Branch-Admin: Automatically uses their branch_id (must be provided in request)
 * Super-Admin: Can specify branch_id for any branch
 * 
 * POST Parameters (JSON):
 * - category_id (int, optional) - Required for update, empty for create
 * - kid (int, optional) - Category display order (auto-generated if 0)
 * - name (string, required) - Category name
 * - description (string, optional) - Category description
 * - kitchen_id (int, optional) - Kitchen ID to assign this category to
 * - terminal (int, required) - Terminal number
 * - branch_id (int/string, required) - Branch ID (MUST be provided)
 * 
 * DELETE Parameters (JSON):
 * - category_id (int, required)
 * - terminal (int, optional)
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
        $category_id = isset($input['category_id']) ? trim($input['category_id']) : '';
        $kid = isset($input['kid']) ? intval($input['kid']) : 0;
        $name = isset($input['name']) ? trim($input['name']) : '';
        $description = isset($input['description']) ? trim($input['description']) : '';
        $kitchen_id = isset($input['kitchen_id']) && $input['kitchen_id'] ? intval($input['kitchen_id']) : null;
        $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
        $branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : null;
        
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
                'message' => 'Category name is required'
            ]);
            exit();
        }
        
        // Validate kitchen_id (optional but recommended)
        // We'll allow null but it's better to have it
        
        if (empty($category_id)) {
            // CREATE NEW CATEGORY
            // Auto-generate kid if not provided or 0
            if ($kid <= 0) {
                // Get next kid for this branch and terminal
                $kid_sql = "SELECT COALESCE(MAX(kid), 0) + 1 AS next_kid 
                           FROM categories 
                           WHERE branch_id = ? AND terminal = ?";
                $kid_stmt = mysqli_prepare($connection, $kid_sql);
                if (!$kid_stmt) {
                    throw new Exception("Error preparing kid statement: " . mysqli_error($connection));
                }
                mysqli_stmt_bind_param($kid_stmt, "ii", $branch_id, $terminal);
                mysqli_stmt_execute($kid_stmt);
                $kid_result = mysqli_stmt_get_result($kid_stmt);
                $kid_row = mysqli_fetch_assoc($kid_result);
                $kid = intval($kid_row['next_kid']);
                mysqli_stmt_close($kid_stmt);
            }
            
            // Check if category name already exists for this branch
            $check_sql = "SELECT category_id FROM categories 
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
                    'error' => 'Category already exists',
                    'message' => 'A category with this name already exists for this branch'
                ]);
                exit();
            }
            mysqli_stmt_close($check_stmt);
            
            // Insert new category
            $insert_sql = "INSERT INTO categories 
                          (kid, name, description, kitchen_id, terminal, branch_id, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $insert_stmt = mysqli_prepare($connection, $insert_sql);
            if (!$insert_stmt) {
                throw new Exception("Error preparing insert statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($insert_stmt, "issiii", $kid, $name, $description, $kitchen_id, $terminal, $branch_id);
            
            if (!mysqli_stmt_execute($insert_stmt)) {
                $error_msg = mysqli_error($connection);
                mysqli_stmt_close($insert_stmt);
                throw new Exception('Failed to create category: ' . $error_msg);
            }
            
            $new_category_id = mysqli_insert_id($connection);
            mysqli_stmt_close($insert_stmt);
            
            // Fetch the created category with branch info
            $fetch_sql = "SELECT 
                            c.category_id,
                            c.kid,
                            c.kitchen_id,
                            c.name,
                            c.description,
                            c.terminal,
                            c.branch_id,
                            b.branch_name,
                            k.title AS kitchen_name
                        FROM categories c
                        LEFT JOIN branches b ON c.branch_id = b.branch_id
                        LEFT JOIN kitchens k ON c.kitchen_id = k.kitchen_id AND c.terminal = k.terminal AND c.branch_id = k.branch_id
                        WHERE c.category_id = ?";
            $fetch_stmt = mysqli_prepare($connection, $fetch_sql);
            if (!$fetch_stmt) {
                throw new Exception("Error preparing fetch statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($fetch_stmt, "i", $new_category_id);
            mysqli_stmt_execute($fetch_stmt);
            $fetch_result = mysqli_stmt_get_result($fetch_stmt);
            $category = mysqli_fetch_assoc($fetch_result);
            
            $branch_name = $category['branch_name'] ?? null;
            if (!$branch_name && $category['branch_id']) {
                $branch_name = 'Branch ' . $category['branch_id'];
            }
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => [
                    'category_id' => intval($category['category_id']),
                    'kid' => intval($category['kid']),
                    'name' => $category['name'],
                    'description' => $category['description'] ?? '',
                    'kitchen_id' => $category['kitchen_id'] ? intval($category['kitchen_id']) : null,
                    'kitchen_name' => $category['kitchen_name'] ?? null,
                    'branch_id' => intval($category['branch_id']),
                    'branch_name' => $branch_name,
                    'terminal' => intval($category['terminal'])
                ]
            ]);
            mysqli_stmt_close($fetch_stmt);
            
        } else {
            // UPDATE EXISTING CATEGORY
            $category_id = intval($category_id);
            
            // Check if category exists
            $check_sql = "SELECT category_id, branch_id FROM categories WHERE category_id = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            if (!$check_stmt) {
                throw new Exception("Error preparing check statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($check_stmt, "i", $category_id);
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
                    'error' => 'Category not found',
                    'message' => 'Category with ID ' . $category_id . ' does not exist'
                ]);
                exit();
            }
            
            $existing = mysqli_fetch_assoc($check_result);
            // Use existing branch_id if branch_id wasn't provided in update
            // This prevents branch-admin from changing branch_id of their categories
            $update_branch_id = $branch_id ? $branch_id : intval($existing['branch_id']);
            mysqli_stmt_close($check_stmt);
            
            // Check if name already exists for another category in the same branch
            $name_check_sql = "SELECT category_id FROM categories 
                              WHERE name = ? AND branch_id = ? AND terminal = ? AND category_id != ?";
            $name_check_stmt = mysqli_prepare($connection, $name_check_sql);
            if (!$name_check_stmt) {
                throw new Exception("Error preparing name check statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($name_check_stmt, "siii", $name, $update_branch_id, $terminal, $category_id);
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
                    'error' => 'Category name already exists',
                    'message' => 'A category with this name already exists for this branch'
                ]);
                exit();
            }
            mysqli_stmt_close($name_check_stmt);
            
            // Update category
            $update_sql = "UPDATE categories 
                          SET name = ?, 
                              description = ?, 
                              kitchen_id = ?,
                              kid = ?,
                              branch_id = ?,
                              updated_at = NOW()
                          WHERE category_id = ? AND terminal = ?";
            $update_stmt = mysqli_prepare($connection, $update_sql);
            if (!$update_stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($update_stmt, "ssiiiii", $name, $description, $kitchen_id, $kid, $update_branch_id, $category_id, $terminal);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                $error_msg = mysqli_error($connection);
                mysqli_stmt_close($update_stmt);
                throw new Exception('Failed to update category: ' . $error_msg);
            }
            
            mysqli_stmt_close($update_stmt);
            
            // Fetch updated category with branch info
            $fetch_sql = "SELECT 
                            c.category_id,
                            c.kid,
                            c.kitchen_id,
                            c.name,
                            c.description,
                            c.terminal,
                            c.branch_id,
                            b.branch_name,
                            k.title AS kitchen_name
                        FROM categories c
                        LEFT JOIN branches b ON c.branch_id = b.branch_id
                        LEFT JOIN kitchens k ON c.kitchen_id = k.kitchen_id AND c.terminal = k.terminal AND c.branch_id = k.branch_id
                        WHERE c.category_id = ?";
            $fetch_stmt = mysqli_prepare($connection, $fetch_sql);
            if (!$fetch_stmt) {
                throw new Exception("Error preparing fetch statement: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($fetch_stmt, "i", $category_id);
            mysqli_stmt_execute($fetch_stmt);
            $fetch_result = mysqli_stmt_get_result($fetch_stmt);
            $category = mysqli_fetch_assoc($fetch_result);
            
            $branch_name = $category['branch_name'] ?? null;
            if (!$branch_name && $category['branch_id']) {
                $branch_name = 'Branch ' . $category['branch_id'];
            }
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => [
                    'category_id' => intval($category['category_id']),
                    'kid' => intval($category['kid']),
                    'name' => $category['name'],
                    'description' => $category['description'] ?? '',
                    'kitchen_id' => $category['kitchen_id'] ? intval($category['kitchen_id']) : null,
                    'kitchen_name' => $category['kitchen_name'] ?? null,
                    'branch_id' => intval($category['branch_id']),
                    'branch_name' => $branch_name,
                    'terminal' => intval($category['terminal'])
                ]
            ]);
            mysqli_stmt_close($fetch_stmt);
        }
        
    } elseif ($method === 'DELETE') {
        // DELETE CATEGORY
        $category_id = isset($input['category_id']) ? intval($input['category_id']) : 0;
        
        if ($category_id <= 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid category_id',
                'message' => 'Category ID is required'
            ]);
            exit();
        }
        
        // Check if category exists
        $check_sql = "SELECT category_id FROM categories WHERE category_id = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        if (!$check_stmt) {
            throw new Exception("Error preparing check statement: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param($check_stmt, "i", $category_id);
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
                'error' => 'Category not found',
                'message' => 'Category does not exist'
            ]);
            exit();
        }
        mysqli_stmt_close($check_stmt);
        
        // Delete category
        $delete_sql = "DELETE FROM categories WHERE category_id = ?";
        $delete_stmt = mysqli_prepare($connection, $delete_sql);
        if (!$delete_stmt) {
            throw new Exception("Error preparing delete statement: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param($delete_stmt, "i", $category_id);
        
        if (!mysqli_stmt_execute($delete_stmt)) {
            $error_msg = mysqli_error($connection);
            mysqli_stmt_close($delete_stmt);
            throw new Exception('Failed to delete category: ' . $error_msg);
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
            'message' => 'Category deleted successfully'
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
    error_log("Category Management Error: " . $e->getMessage());
    
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
