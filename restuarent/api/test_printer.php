<?php
/**
 * Test Printer Connection - Diagnostic Tool
 * 
 * This endpoint helps diagnose USB printer connection issues
 * 
 * Usage:
 *   GET /api/test_printer.php?printer_id=1
 *   OR
 *   POST /api/test_printer.php
 *   Body: {"printer_id": 1}
 */

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
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}

if (!isset($connection) || !$connection) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
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

if ($printer_id <= 0) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Printer ID is required',
        'usage' => 'GET /api/test_printer.php?printer_id=1 OR POST with {"printer_id": 1}'
    ]);
    exit();
}

// Get printer from database
$sql = "SELECT * FROM printers WHERE printer_id = ? AND status = 'active'";
$stmt = mysqli_prepare($connection, $sql);
if (!$stmt) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["success" => false, "message" => "Error: " . mysqli_error($connection)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $printer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$printer = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$printer) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Printer not found or inactive',
        'printer_id' => $printer_id
    ]);
    exit();
}

$results = [
    'printer_info' => [
        'printer_id' => $printer['printer_id'],
        'name' => $printer['name'],
        'connection_type' => $printer['connection_type'],
        'usb_port' => $printer['usb_port'],
        'printer_name' => $printer['printer_name'],
        'type' => $printer['type']
    ],
    'tests' => []
];

// Test 1: Check USB port format
if ($printer['connection_type'] === 'usb') {
    if (!empty($printer['usb_port'])) {
        $port_name = strtoupper(trim($printer['usb_port']));
        $valid_format = preg_match('/^(COM\d+|USB\d+)$/i', $port_name);
        $results['tests']['port_format'] = [
            'test' => 'USB Port Format',
            'port' => $port_name,
            'valid' => $valid_format,
            'message' => $valid_format ? 'Port format is valid' : 'Invalid port format (expected COM1, COM2, USB001, USB002, etc.)'
        ];
        
        // Test 2: Try to open port (COM ports only)
        if ($valid_format && preg_match('/^COM\d+$/i', $port_name)) {
            $handle = @fopen($port_name, 'w');
            if ($handle) {
                @fclose($handle);
                $results['tests']['port_access'] = [
                    'test' => 'Port Access (COM)',
                    'port' => $port_name,
                    'accessible' => true,
                    'message' => 'Port is accessible'
                ];
            } else {
                $results['tests']['port_access'] = [
                    'test' => 'Port Access (COM)',
                    'port' => $port_name,
                    'accessible' => false,
                    'message' => 'Cannot open port. Make sure printer is connected and port is correct.'
                ];
            }
        }
    }
    
    // Test 3: Check Windows printer name
    if (!empty($printer['printer_name'])) {
        // Try to get list of Windows printers
        $printer_list_cmd = 'wmic printer get name 2>&1';
        $output = [];
        $return_var = 0;
        exec($printer_list_cmd, $output, $return_var);
        
        $printer_found = false;
        if ($return_var === 0) {
            foreach ($output as $line) {
                if (stripos($line, trim($printer['printer_name'])) !== false) {
                    $printer_found = true;
                    break;
                }
            }
        }
        
        $results['tests']['windows_printer'] = [
            'test' => 'Windows Printer Name',
            'printer_name' => $printer['printer_name'],
            'found' => $printer_found,
            'message' => $printer_found ? 'Printer found in Windows' : 'Printer not found in Windows. Check printer name matches exactly.'
        ];
    }
}

// Test 4: Temp directory
$temp_dir = sys_get_temp_dir();
$temp_writable = is_writable($temp_dir);
$results['tests']['temp_directory'] = [
    'test' => 'Temporary Directory',
    'path' => $temp_dir,
    'writable' => $temp_writable,
    'message' => $temp_writable ? 'Temporary directory is writable' : 'Temporary directory is not writable'
];

// Test 5: Test print command availability
$test_print_cmd = 'print /? 2>&1';
$output = [];
$return_var = 0;
exec($test_print_cmd, $output, $return_var);
$results['tests']['print_command'] = [
    'test' => 'Windows Print Command',
    'available' => $return_var === 0 || $return_var === 1, // 0 or 1 means command exists
    'message' => ($return_var === 0 || $return_var === 1) ? 'Print command is available' : 'Print command may not be available'
];

// Overall status
$all_tests_passed = true;
foreach ($results['tests'] as $test) {
    if (isset($test['valid']) && !$test['valid']) {
        $all_tests_passed = false;
    }
    if (isset($test['accessible']) && !$test['accessible']) {
        $all_tests_passed = false;
    }
    if (isset($test['found']) && !$test['found']) {
        $all_tests_passed = false;
    }
    if (isset($test['writable']) && !$test['writable']) {
        $all_tests_passed = false;
    }
}

ob_end_clean();
header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    'success' => $all_tests_passed,
    'message' => $all_tests_passed ? 'All tests passed' : 'Some tests failed - check details below',
    'results' => $results,
    'recommendations' => [
        '1' => 'If port format is invalid, check Device Manager for correct port name',
        '2' => 'If port is not accessible, ensure printer is connected and drivers are installed',
        '3' => 'If Windows printer not found, use exact name from Control Panel > Devices and Printers',
        '4' => 'Try using Windows printer name instead of USB port if port access fails',
        '5' => 'Make sure printer is powered on and not in error state'
    ]
], JSON_PRETTY_PRINT);

exit();
?>

