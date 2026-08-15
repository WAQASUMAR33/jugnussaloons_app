<?php
/**
 * Printer Management API
 * Handles CRUD operations for printers
 * Supports both JSON and form data
 * Supports branch-specific printers
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

// Check if printers table exists, if not create it
$checkTable = mysqli_query($connection, "SHOW TABLES LIKE 'printers'");
if (mysqli_num_rows($checkTable) == 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS printers (
        printer_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        ip_address VARCHAR(255) DEFAULT NULL,
        port INT DEFAULT 9100,
        connection_type VARCHAR(20) DEFAULT 'network',
        usb_port VARCHAR(50) DEFAULT NULL,
        printer_name VARCHAR(255) DEFAULT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'receipt',
        terminal INT NOT NULL DEFAULT 1,
        branch_id INT DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_branch_terminal (branch_id, terminal),
        INDEX idx_type (type)
    )";
    mysqli_query($connection, $createTable);
} else {
    // Check if branch_id column exists, if not add it
    $checkBranchId = mysqli_query($connection, "SHOW COLUMNS FROM printers LIKE 'branch_id'");
    if (mysqli_num_rows($checkBranchId) == 0) {
        mysqli_query($connection, "ALTER TABLE printers ADD COLUMN branch_id INT DEFAULT NULL AFTER terminal");
        mysqli_query($connection, "ALTER TABLE printers ADD INDEX idx_branch_terminal (branch_id, terminal)");
    }
    
    // Check if connection_type column exists, if not add it
    $checkColumn = mysqli_query($connection, "SHOW COLUMNS FROM printers LIKE 'connection_type'");
    if (mysqli_num_rows($checkColumn) == 0) {
        mysqli_query($connection, "ALTER TABLE printers ADD COLUMN connection_type VARCHAR(20) DEFAULT 'network' AFTER port");
    }
    
    // Check if usb_port column exists, if not add it
    $checkUsbPort = mysqli_query($connection, "SHOW COLUMNS FROM printers LIKE 'usb_port'");
    if (mysqli_num_rows($checkUsbPort) == 0) {
        mysqli_query($connection, "ALTER TABLE printers ADD COLUMN usb_port VARCHAR(50) DEFAULT NULL AFTER connection_type");
    }
    
    // Check if printer_name column exists, if not add it
    $checkPrinterName = mysqli_query($connection, "SHOW COLUMNS FROM printers LIKE 'printer_name'");
    if (mysqli_num_rows($checkPrinterName) == 0) {
        mysqli_query($connection, "ALTER TABLE printers ADD COLUMN printer_name VARCHAR(255) DEFAULT NULL AFTER usb_port");
    }
    
    // Update ip_address to allow NULL for USB printers
    $checkIpNull = mysqli_query($connection, "SHOW COLUMNS FROM printers WHERE Field='ip_address' AND Null='NO'");
    if (mysqli_num_rows($checkIpNull) > 0) {
        mysqli_query($connection, "ALTER TABLE printers MODIFY COLUMN ip_address VARCHAR(255) DEFAULT NULL");
    }
}

// Wrap main logic in try-catch
try {
// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve input data
    $printer_id = $input['printer_id'] ?? ($_POST['printer_id'] ?? '');
    $name = $input['name'] ?? ($_POST['name'] ?? '');
    $ip_address = $input['ip_address'] ?? ($_POST['ip_address'] ?? '');
    $port = isset($input['port']) && $input['port'] !== '' ? intval($input['port']) : (isset($_POST['port']) && $_POST['port'] !== '' ? intval($_POST['port']) : null);
    $connection_type = $input['connection_type'] ?? ($_POST['connection_type'] ?? 'network');
    $usb_port = $input['usb_port'] ?? ($_POST['usb_port'] ?? '');
    $printer_name = $input['printer_name'] ?? ($_POST['printer_name'] ?? '');
    $type = $input['type'] ?? ($_POST['type'] ?? 'receipt');
    $terminal = isset($input['terminal']) ? intval($input['terminal']) : (isset($_POST['terminal']) ? intval($_POST['terminal']) : 1);
    $branch_id = isset($input['branch_id']) ? (empty($input['branch_id']) ? null : intval($input['branch_id'])) : (isset($_POST['branch_id']) ? (empty($_POST['branch_id']) ? null : intval($_POST['branch_id'])) : null);
    $status = $input['status'] ?? ($_POST['status'] ?? 'active');

    // Validate connection_type
    if (!in_array($connection_type, ['network', 'usb'])) {
        $connection_type = 'network';
    }

    // Validate required fields based on connection type
    if (empty($name) || empty($terminal)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["success" => false, "message" => "Name and terminal are required."]);
        exit;
    }
    
    // For network printers, IP address and port are required
    if ($connection_type === 'network') {
        if (empty($ip_address)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            echo json_encode(["success" => false, "message" => "IP address is required for network printers."]);
            exit;
        }
        // Set default port for network printers if not provided
        if ($port === null || $port <= 0) {
            $port = 9100;
        }
    }
    
    // For USB printers, either usb_port or printer_name is required
    // ip_address and port are OPTIONAL for USB printers
    if ($connection_type === 'usb') {
        if (empty($usb_port) && empty($printer_name)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            echo json_encode(["success" => false, "message" => "USB port (COM port) or printer name is required for USB printers."]);
            exit;
        }
        
        // For USB printers, ip_address and port are optional
        // Only set placeholder values if database requires NOT NULL (will be set before INSERT/UPDATE)
        // Allow NULL/empty values from frontend
    }
    
    // Set placeholder values for USB printers only when inserting/updating (to satisfy NOT NULL constraint if exists)
    // These values won't be used for USB printing
    $ip_address_for_db = $ip_address;
    $port_for_db = $port;
    
    if ($connection_type === 'usb') {
        // Only set placeholders if values are empty/null (database may require NOT NULL)
        if (empty($ip_address_for_db)) {
            $ip_address_for_db = 'USB'; // Placeholder value for USB printers
        }
        if ($port_for_db === null || $port_for_db <= 0) {
            $port_for_db = 0; // Placeholder port for USB printers
        }
    }
    
    // For kitchen printers, force network connection
    if ($type === 'kitchen' && $connection_type !== 'network') {
        $connection_type = 'network';
        if (empty($ip_address)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            echo json_encode(["success" => false, "message" => "Kitchen printers must use network connection with IP address."]);
            exit;
        }
    }

    if (empty($printer_id)) {
        // Insert query
        // For USB printers, ip_address and port use placeholder values to satisfy NOT NULL constraint if database requires it
        $sql = "INSERT INTO printers (name, ip_address, port, connection_type, usb_port, printer_name, type, terminal, branch_id, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param(
            $stmt,
            "ssissssiii",
            $name,
            $ip_address_for_db,  // Use placeholder for USB printers if needed
            $port_for_db,        // Use placeholder for USB printers if needed
            $connection_type,
            $usb_port,
            $printer_name,
            $type,
            $terminal,
            $branch_id,
            $status
        );
    } else {
        // Update query
        $sql = "UPDATE printers SET name = ?, ip_address = ?, port = ?, connection_type = ?, usb_port = ?, printer_name = ?, type = ?, terminal = ?, branch_id = ?, status = ?, updated_at = NOW() WHERE printer_id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param(
            $stmt,
            "ssissssiiii",
            $name,
            $ip_address_for_db,  // Use placeholder for USB printers if needed
            $port_for_db,        // Use placeholder for USB printers if needed
            $connection_type,
            $usb_port,
            $printer_name,
            $type,
            $terminal,
            $branch_id,
            $status,
            $printer_id
        );
    }

    // Execute the query
    try {
        if (mysqli_stmt_execute($stmt)) {
            $message = empty($printer_id) ? "Printer added successfully." : "Printer updated successfully.";
            $printer_id_result = empty($printer_id) ? mysqli_insert_id($connection) : $printer_id;
            
            mysqli_stmt_close($stmt);
            
            // Fetch the complete printer data including branch_id
            $fetch_sql = "SELECT * FROM printers WHERE printer_id = ?";
            $fetch_stmt = mysqli_prepare($connection, $fetch_sql);
            if ($fetch_stmt) {
                mysqli_stmt_bind_param($fetch_stmt, "i", $printer_id_result);
                mysqli_stmt_execute($fetch_stmt);
                $fetch_result = mysqli_stmt_get_result($fetch_stmt);
                $printer_data = mysqli_fetch_assoc($fetch_result);
                mysqli_stmt_close($fetch_stmt);
            } else {
                $printer_data = null;
            }
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            
            $response = [
                "success" => true,
                "message" => $message,
                "printer_id" => $printer_id_result
            ];
            
            // Include full printer data if available (including branch_id)
            if ($printer_data) {
                $response["printer"] = $printer_data;
                $response["branch_id"] = $printer_data['branch_id'] ?? null;
            } else {
                // Fallback: include branch_id from input if fetch failed
                $response["branch_id"] = $branch_id;
            }
            
            echo json_encode($response);
        } else {
            $error = mysqli_stmt_error($stmt);
            $db_error = mysqli_error($connection);
            mysqli_stmt_close($stmt);
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            $error_msg = !empty($error) ? $error : (!empty($db_error) ? $db_error : "Unknown database error");
            error_log("Printer Management Error: " . $error_msg);
            echo json_encode(["success" => false, "message" => "Error: " . $error_msg]);
            exit();
        }
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
        error_log("Printer Management Exception: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        exit();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get printer_id from request
    $printer_id = $input['printer_id'] ?? ($_POST['printer_id'] ?? '');
    
    // Try to get from query string as well
    if (empty($printer_id) && isset($_GET['printer_id'])) {
        $printer_id = $_GET['printer_id'];
    }

    if (empty($printer_id)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["success" => false, "message" => "Printer ID is required for deletion."]);
        exit;
    }

    // Check if printer is assigned to any kitchen
    $check_sql = "SELECT COUNT(*) as count FROM kitchens WHERE printer = ?";
    $check_stmt = mysqli_prepare($connection, $check_sql);
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "i", $printer_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_data = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);
        
        if ($check_data['count'] > 0) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header("Content-Type: application/json; charset=UTF-8");
            }
            echo json_encode(["success" => false, "message" => "Cannot delete printer. It is assigned to " . $check_data['count'] . " kitchen(s). Please unassign it first."]);
            exit;
        }
    }

    // Delete query
    $sql = "DELETE FROM printers WHERE printer_id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["success" => false, "message" => "Error preparing statement: " . mysqli_error($connection)]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "i", $printer_id);

    // Execute the query
    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_affected_rows($connection);
        mysqli_stmt_close($stmt);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        if ($affected > 0) {
            echo json_encode(["success" => true, "message" => "Printer deleted successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "No printer found with the provided Printer ID."]);
        }
    } else {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode(["success" => false, "message" => "Error: " . $error]);
    }
} else {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    error_log("Printer Management Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode([
        "success" => false,
        "message" => "Server Error: " . $e->getMessage(),
        "error_type" => get_class($e)
    ]);
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    error_log("Printer Management Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode([
        "success" => false,
        "message" => "Server Error: " . $e->getMessage(),
        "error_type" => "Fatal Error"
    ]);
}

exit();
?>
