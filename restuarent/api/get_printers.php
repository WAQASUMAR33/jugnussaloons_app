<?php
/**
 * Get Printers API
 * Returns printers for a specific terminal and optionally branch
 * Supports both JSON and form data
 * Supports GET and POST methods
 */
require_once 'cors_headers.php';

// Disable error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

ob_start();

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
            "success" => false,
            "message" => "Server Error: " . $error['message'],
            "file" => $error['file'],
            "line" => $error['line']
        ]);
        exit();
    }
});

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

// Verify database connection is alive
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

// Get input data - handle both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST; // Fallback to form data
}

// Also check GET parameters
if (empty($input) && !empty($_GET)) {
    $input = $_GET;
}

// Get terminal and branch_id from input data
$terminal = isset($input["terminal"]) ? intval($input["terminal"]) : (isset($_GET["terminal"]) ? intval($_GET["terminal"]) : 1);
$branch_id = isset($input["branch_id"]) ? (empty($input["branch_id"]) ? null : intval($input["branch_id"])) : (isset($_GET["branch_id"]) ? (empty($_GET["branch_id"]) ? null : intval($_GET["branch_id"])) : null);
$type = isset($input["type"]) ? trim($input["type"]) : (isset($_GET["type"]) ? trim($_GET["type"]) : '');

// Check if printers table exists, if not return empty array
$checkTable = mysqli_query($connection, "SHOW TABLES LIKE 'printers'");
if (mysqli_num_rows($checkTable) == 0) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => true, "data" => []]);
    exit();
}

// Build query based on filters
$sql = "SELECT * FROM printers WHERE terminal = ?";
$params = [$terminal];
$types = "i";

if ($branch_id !== null && $branch_id > 0) {
    $sql .= " AND (branch_id = ? OR branch_id IS NULL)";
    $params[] = $branch_id;
    $types .= "i";
}

if (!empty($type)) {
    $sql .= " AND type = ?";
    $params[] = $type;
    $types .= "s";
}

$sql .= " ORDER BY branch_id ASC, printer_id DESC";

$stmt = mysqli_prepare($connection, $sql);

if ($stmt) {
    try {
        if (count($params) > 1) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        } else {
            mysqli_stmt_bind_param($stmt, $types, $terminal);
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt) ?: mysqli_error($connection);
            mysqli_stmt_close($stmt);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            error_log("Get Printers Query Error: " . $error);
            echo json_encode(["success" => false, "message" => "Error executing query: " . $error]);
            exit();
        }
        
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result === false) {
            $error = mysqli_stmt_error($stmt) ?: mysqli_error($connection);
            mysqli_stmt_close($stmt);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            error_log("Get Printers Result Error: " . $error);
            echo json_encode(["success" => false, "message" => "Error getting results: " . $error]);
            exit();
        }
        
        // Create array
        $emparray = array();
        while($row = mysqli_fetch_assoc($result)) {
            $emparray[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["success" => true, "data" => $emparray, "count" => count($emparray)]);
    } catch (Exception $e) {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        error_log("Get Printers Exception: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        exit();
    }
} else {
    $error = mysqli_error($connection);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    error_log("Get Printers Prepare Error: " . $error);
    echo json_encode(["success" => false, "message" => "Error preparing query: " . $error]);
}

exit();
?>
