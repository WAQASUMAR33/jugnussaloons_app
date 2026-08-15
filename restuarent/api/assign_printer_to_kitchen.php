<?php
/**
 * Assign Printer to Kitchen API
 * Links a printer to a kitchen for a specific branch
 * 
 * POST Parameters (JSON):
 * - kitchen_id (int, required) - Kitchen ID
 * - printer_id (int, required) - Printer ID
 * - branch_id (int, required) - Branch ID
 * - terminal (int, required) - Terminal number
 * 
 * Returns:
 * {
 *   "success": true,
 *   "message": "Printer assigned to kitchen successfully",
 *   "data": {
 *     "kitchen_id": 1,
 *     "printer_id": 2,
 *     "kitchen_name": "Fast Food Kitchen",
 *     "printer_name": "Kitchen Printer 1",
 *     "printer_ip": "192.168.1.101"
 *   }
 * }
 */
require_once 'cors_headers.php';

// Disable error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

ob_start();

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

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'POST') {
        throw new Exception("Only POST method is allowed");
    }
    
    // Get input data
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    $kitchen_id = isset($input['kitchen_id']) ? intval($input['kitchen_id']) : 0;
    $printer_id = isset($input['printer_id']) ? intval($input['printer_id']) : 0;
    $branch_id = isset($input['branch_id']) ? intval($input['branch_id']) : 0;
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : 1;
    
    // Validate required fields
    if (empty($kitchen_id) || $kitchen_id <= 0) {
        throw new Exception("Kitchen ID is required");
    }
    
    if (empty($printer_id) || $printer_id <= 0) {
        throw new Exception("Printer ID is required");
    }
    
    if (empty($branch_id) || $branch_id <= 0) {
        throw new Exception("Branch ID is required");
    }
    
    // Verify kitchen exists and belongs to branch
    $kitchen_sql = "SELECT kitchen_id, title, code, branch_id, terminal FROM kitchens WHERE kitchen_id = ? AND branch_id = ? AND terminal = ?";
    $kitchen_stmt = mysqli_prepare($connection, $kitchen_sql);
    if (!$kitchen_stmt) {
        throw new Exception("Error preparing kitchen query: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($kitchen_stmt, "iii", $kitchen_id, $branch_id, $terminal);
    mysqli_stmt_execute($kitchen_stmt);
    $kitchen_result = mysqli_stmt_get_result($kitchen_stmt);
    $kitchen = mysqli_fetch_assoc($kitchen_result);
    mysqli_stmt_close($kitchen_stmt);
    
    if (!$kitchen) {
        throw new Exception("Kitchen not found or does not belong to the specified branch");
    }
    
    // Verify printer exists and is a network printer (required for kitchen)
    $printer_sql = "SELECT printer_id, name, ip_address, port, connection_type, branch_id, type 
                   FROM printers 
                   WHERE printer_id = ? AND connection_type = 'network'";
    $printer_stmt = mysqli_prepare($connection, $printer_sql);
    if (!$printer_stmt) {
        throw new Exception("Error preparing printer query: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($printer_stmt, "i", $printer_id);
    mysqli_stmt_execute($printer_stmt);
    $printer_result = mysqli_stmt_get_result($printer_stmt);
    $printer = mysqli_fetch_assoc($printer_result);
    mysqli_stmt_close($printer_stmt);
    
    if (!$printer) {
        throw new Exception("Printer not found or is not a network printer. Kitchen printers must use network connection.");
    }
    
    // Check if printer belongs to the same branch (or is global/null branch_id)
    if (!empty($printer['branch_id']) && $printer['branch_id'] != $branch_id) {
        throw new Exception("Printer belongs to a different branch. Please select a printer for branch ID: " . $branch_id);
    }
    
    // Update kitchen with printer assignment
    $update_sql = "UPDATE kitchens SET printer = ?, updated_at = NOW() WHERE kitchen_id = ? AND branch_id = ? AND terminal = ?";
    $update_stmt = mysqli_prepare($connection, $update_sql);
    if (!$update_stmt) {
        throw new Exception("Error preparing update statement: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($update_stmt, "iiii", $printer_id, $kitchen_id, $branch_id, $terminal);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        $error = mysqli_stmt_error($update_stmt);
        mysqli_stmt_close($update_stmt);
        throw new Exception("Error updating kitchen: " . $error);
    }
    
    $affected = mysqli_affected_rows($connection);
    mysqli_stmt_close($update_stmt);
    
    if ($affected <= 0) {
        throw new Exception("No changes made. Kitchen may not exist or already has this printer assigned.");
    }
    
    // Get updated kitchen with printer info
    $get_sql = "SELECT k.kitchen_id, k.title, k.code, k.printer, k.branch_id, k.terminal,
                       p.name as printer_name, p.ip_address, p.port
                FROM kitchens k
                LEFT JOIN printers p ON k.printer = p.printer_id
                WHERE k.kitchen_id = ? AND k.branch_id = ? AND k.terminal = ?";
    $get_stmt = mysqli_prepare($connection, $get_sql);
    mysqli_stmt_bind_param($get_stmt, "iii", $kitchen_id, $branch_id, $terminal);
    mysqli_stmt_execute($get_stmt);
    $get_result = mysqli_stmt_get_result($get_stmt);
    $updated_kitchen = mysqli_fetch_assoc($get_result);
    mysqli_stmt_close($get_stmt);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Printer assigned to kitchen successfully",
        "data" => [
            "kitchen_id" => intval($updated_kitchen['kitchen_id']),
            "printer_id" => intval($updated_kitchen['printer']),
            "kitchen_name" => $updated_kitchen['title'],
            "printer_name" => $updated_kitchen['printer_name'] ?? 'Not assigned',
            "printer_ip" => $updated_kitchen['ip_address'] ?? 'Not configured',
            "printer_port" => intval($updated_kitchen['port'] ?? 9100),
            "branch_id" => intval($updated_kitchen['branch_id'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Assign Printer to Kitchen Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
}

exit();
?>

