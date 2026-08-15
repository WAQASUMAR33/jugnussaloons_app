<?php
/**
 * Test Print USB Printer - Simple Test Endpoint
 * 
 * This endpoint sends a simple test receipt to USB printer
 * 
 * Usage:
 *   POST /api/test_print_usb.php
 *   Body: {"printer_id": 1} OR {"order_id": 123}
 */

// Handle OPTIONS request first (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit();
}

require_once 'cors_headers.php';

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

ob_start();

try {
    include("config.php");
} catch (Exception $e) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}

if (!isset($connection) || !$connection) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}
if (empty($input) && !empty($_GET)) {
    $input = $_GET;
}

$printer_id = isset($input['printer_id']) ? intval($input['printer_id']) : 0;
$order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;

// Get printer from database
$printer = null;
if ($printer_id > 0) {
    $sql = "SELECT * FROM printers WHERE printer_id = ? AND connection_type = 'usb'";
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $printer_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $printer = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
} else {
    // Find first active USB receipt printer
    $sql = "SELECT * FROM printers WHERE connection_type = 'usb' AND type = 'receipt' AND status = 'active' ORDER BY printer_id DESC LIMIT 1";
    $result = mysqli_query($connection, $sql);
    if ($result) {
        $printer = mysqli_fetch_assoc($result);
    }
}

if (!$printer) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'USB printer not found. Make sure printer exists and connection_type is "usb".',
        'hint' => 'Check printer status in database. Status should be "active".'
    ]);
    exit();
}

// Check printer status - allow if status is 'active' or '1' (some systems use 1 for active)
$printer_status = strtolower(trim($printer['status'] ?? ''));
$is_active = ($printer_status === 'active' || $printer_status === '1' || $printer_status === 'enabled');

if (!$is_active) {
    // Try to auto-fix: Update status to active
    $update_sql = "UPDATE printers SET status = 'active' WHERE printer_id = ?";
    $update_stmt = mysqli_prepare($connection, $update_sql);
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, "i", $printer['printer_id']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        // Re-fetch printer
        $sql = "SELECT * FROM printers WHERE printer_id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $printer['printer_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $printer = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        }
    }
    
    // Check again after update attempt
    $printer_status = strtolower(trim($printer['status'] ?? ''));
    $is_active = ($printer_status === 'active' || $printer_status === '1' || $printer_status === 'enabled');
    
    if (!$is_active) {
        ob_end_clean();
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Printer is not active. Status: ' . ($printer['status'] ?? 'unknown'),
            'printer_id' => $printer['printer_id'],
            'current_status' => $printer['status'] ?? 'unknown',
            'fix' => 'Run this SQL: UPDATE printers SET status = "active" WHERE printer_id = ' . $printer['printer_id'],
            'auto_fix_attempted' => true
        ]);
        exit();
    }
}

// Get USB port or printer name
$usb_port = $printer['usb_port'] ?? '';
$printer_name = $printer['printer_name'] ?? '';

if (empty($usb_port) && empty($printer_name)) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'USB printer configuration incomplete. Set either usb_port or printer_name.',
        'printer_id' => $printer['printer_id']
    ]);
    exit();
}

// Generate simple test receipt
$receipt = chr(27) . chr(64); // Initialize printer
$receipt .= chr(27) . chr(33) . chr(56); // Double height and width
$receipt .= chr(27) . chr(69) . chr(1); // Bold
$receipt .= chr(27) . chr(97) . chr(1); // Center alignment
$receipt .= "TEST PRINT\n";
$receipt .= chr(27) . chr(33) . chr(0); // Reset font size
$receipt .= chr(27) . chr(69) . chr(0); // Disable bold
$receipt .= "--------------------------------\n";
$receipt .= chr(27) . chr(97) . chr(0); // Left alignment
$receipt .= "Printer: " . $printer['name'] . "\n";
$receipt .= "Date: " . date('Y-m-d H:i:s') . "\n";
$receipt .= "--------------------------------\n";
$receipt .= "This is a test print.\n";
$receipt .= "If you can see this,\n";
$receipt .= "your printer is working!\n";
$receipt .= "--------------------------------\n";
$receipt .= "\n\n\n\n";
$receipt .= chr(29) . chr(86) . chr(1); // Cut paper

// Try to print
$print_success = false;
$print_error = '';
$print_method = '';

// Method 1: Try USB port
if (!empty($usb_port)) {
    $port_name = strtoupper(trim($usb_port));
    
    if (preg_match('/^(COM\d+|USB\d+)$/i', $port_name)) {
        // Try direct port access (for COM ports)
        if (preg_match('/^COM\d+$/i', $port_name)) {
            $handle = @fopen($port_name, 'w');
            if ($handle) {
                $bytes_written = @fwrite($handle, $receipt);
                @fclose($handle);
                
                if ($bytes_written !== false && $bytes_written > 0) {
                    $print_success = true;
                    $print_method = 'com_port_direct';
                }
            }
        }
        
        // Try Windows print command
        if (!$print_success) {
            $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_print_' . time() . '.txt';
            $file_written = @file_put_contents($temp_file, $receipt);
            
            if ($file_written !== false) {
                $print_cmd = 'print /D:"' . str_replace('"', '""', $port_name) . '" "' . $temp_file . '" 2>&1';
                $output = [];
                $return_var = 0;
                exec($print_cmd, $output, $return_var);
                @unlink($temp_file);
                
                if ($return_var === 0) {
                    $print_success = true;
                    $print_method = 'usb_port_via_print';
                } else {
                    $print_error = "Print command failed: " . implode(' ', $output);
                }
            }
        }
    }
}

// Method 2: Try Windows printer name
if (!$print_success && !empty($printer_name)) {
    $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_print_' . time() . '.txt';
    $file_written = @file_put_contents($temp_file, $receipt);
    
    if ($file_written !== false) {
        $print_cmd = 'print /D:"' . str_replace('"', '""', $printer_name) . '" "' . $temp_file . '" 2>&1';
        $output = [];
        $return_var = 0;
        exec($print_cmd, $output, $return_var);
        @unlink($temp_file);
        
        if ($return_var === 0) {
            $print_success = true;
            $print_method = 'windows_printer_name';
        } else {
            if (empty($print_error)) {
                $print_error = "Print command failed: " . implode(' ', $output);
            }
        }
    }
}

ob_end_clean();
header("Content-Type: application/json; charset=UTF-8");

if ($print_success) {
    echo json_encode([
        'success' => true,
        'message' => 'Test print sent successfully!',
        'printer_id' => $printer['printer_id'],
        'printer_name' => $printer['name'],
        'method' => $print_method,
        'usb_port' => $usb_port,
        'windows_printer_name' => $printer_name
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send test print: ' . ($print_error ?: 'Unknown error'),
        'printer_id' => $printer['printer_id'],
        'printer_name' => $printer['name'],
        'usb_port' => $usb_port,
        'windows_printer_name' => $printer_name,
        'troubleshooting' => [
            '1' => 'Check if printer is connected and powered on',
            '2' => 'Verify USB port is correct (check Device Manager)',
            '3' => 'Try using Windows printer name instead of USB port',
            '4' => 'Check printer drivers are installed',
            '5' => 'Ensure printer is not set to Offline in Windows'
        ]
    ]);
}

exit();
?>

