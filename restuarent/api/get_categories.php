<?php

/**
 * Get Categories API - Multi-Branch Support
 * Returns list of all categories with kitchen information
 * * Branch-Admin: Returns only their branch's categories (requires branch_id)
 * Super-Admin: Returns all categories with branch info (no branch_id or branch_id = null)
 * * POST Parameters:
 * - terminal (int, optional) - Terminal number (default: 1)
 * - branch_id (int/string, optional) - Branch ID (if null/empty, returns all categories for super-admin)
 */

// Start output buffer tracking at the very first execution boundary line
ob_start();

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
            http_response_code(500); // FIX: Enforce correct HTTP Server Error status
        }
        echo json_encode(["success" => false, "message" => "Fatal runtime error occurred."]);
        exit();
    }
});

require_once 'cors_headers.php';

try {
    include("config.php");
} catch (Throwable $e) { // FIX: Catches all system configurations and loader bugs uniformly
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(500);
    }
    echo json_encode(["success" => false, "message" => "Internal configuration infrastructure error."]);
    exit();
}

if (!isset($connection) || !$connection) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(500);
    }
    $db_err = !empty($GLOBALS['db_connection_error']) ? $GLOBALS['db_connection_error'] : "Database link unavailable.";
    echo json_encode(["success" => false, "message" => $db_err]);
    exit();
}

try {
    // Get JSON input
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    // Handle both POST body and GET parameters
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    // Handle GET requests overriding parameters
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = $_GET;
    }
    
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    $branch_id_input = isset($input['branch_id']) ? $input['branch_id'] : null;
    
    // Convert branch_id to integer or null
    if ($branch_id_input === '' || $branch_id_input === 'null' || $branch_id_input === 'undefined' || $branch_id_input === null) {
        $branch_id = null;
    } else {
        $branch_id = intval($branch_id_input);
        if ($branch_id <= 0) {
            $branch_id = null;
        }
    }
    
    // Detect database schema features (multi-branch vs single-branch)
    $has_branches = false;
    $has_branch_id_col = false;
    $has_kitchen_id_col = false;
    $has_kid_col = false;

    $check_branches = @mysqli_query($connection, "SHOW TABLES LIKE 'branches'");
    if ($check_branches && mysqli_num_rows($check_branches) > 0) {
        $has_branches = true;
    }

    $check_cat_cols = @mysqli_query($connection, "SHOW COLUMNS FROM categories");
    if ($check_cat_cols) {
        while ($col = mysqli_fetch_assoc($check_cat_cols)) {
            if ($col['Field'] === 'branch_id') $has_branch_id_col = true;
            if ($col['Field'] === 'kitchen_id') $has_kitchen_id_col = true;
            if ($col['Field'] === 'kid') $has_kid_col = true;
        }
    }

    if ($has_branches && $has_branch_id_col && $has_kitchen_id_col) {
        if ($branch_id !== null) {
            $sql = "SELECT 
                        c.category_id,
                        " . ($has_kid_col ? "c.kid" : "0 AS kid") . ",
                        c.kitchen_id,
                        c.name,
                        c.description,
                        c.terminal,
                        c.branch_id,
                        b.branch_name,
                        k.title AS kitchen_name,
                        k.code AS kitchen_code
                    FROM categories c
                    LEFT JOIN branches b ON c.branch_id = b.branch_id
                    LEFT JOIN kitchens k ON c.kitchen_id = k.kitchen_id AND c.terminal = k.terminal AND c.branch_id = k.branch_id
                    WHERE c.branch_id = ? AND c.terminal = ?
                    ORDER BY c.name ASC";
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                throw new Exception("Error preparing query: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($stmt, "ii", $branch_id, $terminal);
        } else {
            $sql = "SELECT 
                        c.category_id,
                        " . ($has_kid_col ? "c.kid" : "0 AS kid") . ",
                        c.kitchen_id,
                        c.name,
                        c.description,
                        c.terminal,
                        c.branch_id,
                        b.branch_name,
                        k.title AS kitchen_name,
                        k.code AS kitchen_code
                    FROM categories c
                    LEFT JOIN branches b ON c.branch_id = b.branch_id
                    LEFT JOIN kitchens k ON c.kitchen_id = k.kitchen_id AND c.terminal = k.terminal AND c.branch_id = k.branch_id
                    ORDER BY c.branch_id ASC, c.name ASC";
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                throw new Exception("Error preparing query: " . mysqli_error($connection));
            }
        }
    } else {
        // Single-branch fallback query
        $kid_select = $has_kid_col ? "c.kid" : "0 AS kid";
        $kitchen_id_select = $has_kitchen_id_col ? "c.kitchen_id" : ($has_kid_col ? "c.kid AS kitchen_id" : "0 AS kitchen_id");
        $kitchen_join_col = $has_kitchen_id_col ? "c.kitchen_id" : ($has_kid_col ? "c.kid" : "0");

        $sql = "SELECT 
                    c.category_id,
                    {$kid_select},
                    {$kitchen_id_select},
                    c.name,
                    c.description,
                    c.terminal,
                    NULL AS branch_id,
                    'Main Branch' AS branch_name,
                    k.title AS kitchen_name,
                    k.code AS kitchen_code
                FROM categories c
                LEFT JOIN kitchens k ON {$kitchen_join_col} = k.kitchen_id AND c.terminal = k.terminal
                ORDER BY c.name ASC";
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            throw new Exception("Error preparing query: " . mysqli_error($connection));
        }
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed generating structural target database records.");
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Failed parsing data records framework.");
    }
    
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $b_name = $row['branch_name'] ?? null;
        if (!$b_name && $row['branch_id']) {
            $b_name = 'Branch ' . $row['branch_id'];
        }
        
        // FIX: Applied htmlspecialchars configuration mapping values to fully insulate output components against XSS injection leaks
        $categories[] = [
            'category_id'   => intval($row['category_id']),
            'id'            => intval($row['category_id']), 
            'kid'           => intval($row['kid'] ?? 0),
            'name'          => htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'category_name' => htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'), 
            'description'   => htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            'kitchen_id'    => isset($row['kitchen_id']) && $row['kitchen_id'] ? intval($row['kitchen_id']) : null,
            'kitchen_name'  => htmlspecialchars($row['kitchen_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'terminal'      => intval($row['terminal']),
            'branch_id'     => $row['branch_id'] ? intval($row['branch_id']) : null,
            'branch_name'   => htmlspecialchars($b_name ?? 'Global', ENT_QUOTES, 'UTF-8')
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(200);
    }
    
    echo json_encode([
        'success' => true,
        'data'    => $categories,
        'count'   => count($categories)
    ]);
    exit();
    
} catch (Throwable $e) {
    error_log("Get Categories Operations Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(500);
    }
    
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to fetch categories',
        'message' => $e->getMessage()
    ]);
    exit();
}
?>