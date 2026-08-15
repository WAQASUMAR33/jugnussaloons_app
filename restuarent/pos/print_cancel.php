<?php
/**
 * Print Cancel/Addition Receipt
 * Prints cancellation or addition receipts to kitchen printers
 */

require_once '../api/cors_headers.php';

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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
    // Get input data - support both POST and JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $id = isset($input["item_id"]) ? intval($input["item_id"]) : (isset($_POST["item_id"]) ? intval($_POST["item_id"]) : 0);
    $title = isset($input["title"]) ? trim($input["title"]) : (isset($_POST["title"]) ? trim($_POST["title"]) : '');
    $qnty = isset($input["qnty"]) ? intval($input["qnty"]) : (isset($_POST["qnty"]) ? intval($_POST["qnty"]) : 0);
    
    if (empty($id) || empty($title)) {
        throw new Exception("Item ID and title are required");
    }

    // Build query with prepared statement
    $sql = "
        SELECT order_items.item_id, order_items.quantity AS original_quantity, 
               tables.table_number as table_name, dishes.name AS dish_name, 
               kitchens.printer AS kitchen_printer, kitchens.title AS kitchen_title,
               orders.comments, orders.table_id AS table_number, 
               orders.order_taker_id, orders.updated_at, orders.order_id 
        FROM order_items 
        INNER JOIN dishes ON order_items.dish_id = dishes.dish_id 
        INNER JOIN categories ON dishes.category_id = categories.category_id 
        INNER JOIN kitchens ON categories.kid = kitchens.kitchen_id 
        INNER JOIN orders ON order_items.order_id = orders.order_id 
        INNER JOIN tables ON orders.table_id = tables.table_id
        WHERE order_items.item_id = ?
    ";
    
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Group items by printer
        $printers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $printer_key = $row['kitchen_printer'] ?? 'default';
            $printers[$printer_key][] = $row;
        }
        
        mysqli_stmt_close($stmt);
        
        $results = [];
        $success_count = 0;
        $error_count = 0;

        // Loop through each printer and generate a receipt
        foreach ($printers as $printer_ip => $items) {
        // Get the order ID, table number, order taker, updated date, and kitchen title from the first item
        $order_id = $items[0]['order_id'];
        $table_number = $items[0]['table_number'];
        $order_taker_id = $items[0]['order_taker_id'];
        $updated_at = $items[0]['updated_at'];
        $kitchen_title = $items[0]['kitchen_title'];
        $order_comments = $items[0]['comments'];

        $tablename = $items[0]['table_name'];



        // Generate receipt content
        $receipt = chr(27) . chr(64); // Initialize printer
        $receipt .= chr(27) . chr(33) . chr(56); // Double height and width
        $receipt .= chr(27) . chr(69) . chr(1); // Bold text
        $receipt .= chr(27) . chr(97) . chr(1); // Center alignment
        $receipt .= "Restaurant Khaas\n"; // Header text
        $receipt .= chr(27) . chr(33) . chr(0); // Reset font size
        $receipt .= chr(27) . chr(69) . chr(0); // Disable bold text
        $receipt .= "--------------------------------\n";

        // Add kitchen title
        $receipt .= chr(27) . chr(33) . chr(56); // Triple size
        $receipt .= chr(27) . chr(69) . chr(1); // Bold text
        $receipt .= "$kitchen_title\n";
        $receipt .= chr(27) . chr(33) . chr(0); // Reset font size
        $receipt .= chr(27) . chr(69) . chr(0); // Disable bold text
        $receipt .= "--------------------------------\n";

        // Add "Order Cancellation" text
        $receipt .= chr(27) . chr(33) . chr(56); // Triple size
        $receipt .= chr(27) . chr(69) . chr(1); // Bold text
        $receipt .= "$title\n";
        $receipt .= chr(27) . chr(33) . chr(0); // Reset font size
        $receipt .= chr(27) . chr(69) . chr(0); // Disable bold text
        $receipt .= "--------------------------------\n";

        // Center KOT Number, Table, Order Taker, and Updated At
        $receipt .= "KOT Number: $order_id\n";
        $receipt .= "Table: $tablename\n";
        $receipt .= "Order Taker: $order_taker_id\n";
        $receipt .= "$updated_at\n";
        $receipt .= "--------------------------------\n";

        $receipt .= chr(27) . chr(97) . chr(1); // Center alignment for items

        // Add order items
        foreach ($items as $item) {
            $receipt .= sprintf("%-20s x %d\n", $item['dish_name'], $qnty);
        }

        $receipt .= "--------------------------------\n";

        // **New Line Added Here**
        $receipt .=  $order_comments. ".\n";
        $receipt .= "--------------------------------\n";

        // Add extra padding at the bottom of the receipt
        $receipt .= "\n\n\n\n\n"; // Add 5 new lines for padding
        $receipt .= chr(29) . chr(86) . chr(0); // Cut the paper (ESC/POS command)

            // Get printer IP from printers table if kitchen.printer is an ID
            $actual_printer_ip = null;
            $printer_port = 9100;
            
            if (is_numeric($printer_ip)) {
                $printer_id = intval($printer_ip);
                $printer_query = "SELECT ip_address, port FROM printers WHERE printer_id = ? AND connection_type = 'network'";
                $printer_stmt = mysqli_prepare($connection, $printer_query);
                if ($printer_stmt) {
                    mysqli_stmt_bind_param($printer_stmt, "i", $printer_id);
                    if (mysqli_stmt_execute($printer_stmt)) {
                        $printer_result = mysqli_stmt_get_result($printer_stmt);
                        $printer_data = mysqli_fetch_assoc($printer_result);
                        if ($printer_data && !empty($printer_data['ip_address'])) {
                            $actual_printer_ip = $printer_data['ip_address'];
                            $printer_port = !empty($printer_data['port']) ? intval($printer_data['port']) : 9100;
                        }
                    }
                    mysqli_stmt_close($printer_stmt);
                }
            } elseif (filter_var($printer_ip, FILTER_VALIDATE_IP)) {
                $actual_printer_ip = $printer_ip;
            }
            
            if (empty($actual_printer_ip)) {
                $error_count++;
                $results[] = [
                    'kitchen' => $kitchen_title,
                    'success' => false,
                    'message' => 'Printer IP not configured'
                ];
                continue;
            }
            
            // Send receipt to the printer
            $socket = @fsockopen($actual_printer_ip, $printer_port, $errno, $errstr, 3);
            
            if ($socket === false) {
                // Try alternative ports
                $alternative_ports = [9100, 515, 631, 9101, 9102];
                foreach ($alternative_ports as $alt_port) {
                    if ($alt_port == $printer_port) continue;
                    $socket = @fsockopen($actual_printer_ip, $alt_port, $errno, $errstr, 2);
                    if ($socket !== false) {
                        $printer_port = $alt_port;
                        break;
                    }
                }
            }

            if ($socket !== false) {
                stream_set_timeout($socket, 2);
                $bytes_written = @fwrite($socket, $receipt);
                @fclose($socket);
                
                if ($bytes_written !== false && $bytes_written > 0) {
                    $success_count++;
                    $results[] = [
                        'kitchen' => $kitchen_title,
                        'success' => true,
                        'message' => 'Receipt sent to printer successfully',
                        'printer_ip' => $actual_printer_ip
                    ];
                } else {
                    $error_count++;
                    $results[] = [
                        'kitchen' => $kitchen_title,
                        'success' => false,
                        'message' => 'Failed to write to printer',
                        'printer_ip' => $actual_printer_ip
                    ];
                }
            } else {
                $error_count++;
                $results[] = [
                    'kitchen' => $kitchen_title,
                    'success' => false,
                    'message' => "Could not connect to printer $actual_printer_ip:$printer_port - $errstr ($errno)",
                    'printer_ip' => $actual_printer_ip
                ];
            }
        }
        
        // Return JSON response
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        
        echo json_encode([
            'success' => $error_count === 0,
            'message' => "Printed to $success_count printer(s), $error_count error(s)",
            'results' => $results,
            'title' => $title
        ]);
    } else {
        throw new Exception("No data found for item ID: $id");
    }
    
} catch (Exception $e) {
    error_log("Print Cancel Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit();
} catch (Error $e) {
    error_log("Print Cancel Fatal Error: " . $e->getMessage());
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
    exit();
}

exit();
?>