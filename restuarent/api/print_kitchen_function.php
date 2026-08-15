<?php
/**
 * Print Kitchen Receipt Function (Reusable)
 * This function can be called directly from other PHP files on the same server
 * Avoids HTTP/CORS issues by using direct function calls
 * 
 * Parameters:
 * - $connection (mysqli) - Database connection
 * - $order_id (int) - Order ID
 * - $kitchen_id (int) - Kitchen ID
 * - $branch_id (int, optional) - Branch ID
 * 
 * Returns:
 * Array with 'success' (bool) and 'message' (string) keys
 */

function print_kitchen_receipt_direct($connection, $order_id, $kitchen_id, $branch_id = 0) {
    try {
        if (empty($order_id) || $order_id <= 0) {
            return ['success' => false, 'message' => 'Order ID is required'];
        }
        
        if (empty($kitchen_id) || $kitchen_id <= 0) {
            return ['success' => false, 'message' => 'Kitchen ID is required'];
        }
        
        // Get kitchen details with printer IP (considering branch_id)
        // Note: We don't select p.branch_id here to avoid errors if column doesn't exist
        // We'll query printer separately with branch_id check if needed
        $kitchen_sql = "SELECT k.kitchen_id, k.title, k.code, k.printer, k.branch_id, k.terminal,
                               p.ip_address, p.port, p.connection_type
                        FROM kitchens k
                        LEFT JOIN printers p ON k.printer = p.printer_id AND p.connection_type = 'network'
                        WHERE k.kitchen_id = ?";
        
        $kitchen_stmt = mysqli_prepare($connection, $kitchen_sql);
        if (!$kitchen_stmt) {
            return ['success' => false, 'message' => 'Error preparing kitchen query: ' . mysqli_error($connection)];
        }
        
        mysqli_stmt_bind_param($kitchen_stmt, "i", $kitchen_id);
        mysqli_stmt_execute($kitchen_stmt);
        $kitchen_result = mysqli_stmt_get_result($kitchen_stmt);
        $kitchen = mysqli_fetch_assoc($kitchen_result);
        mysqli_stmt_close($kitchen_stmt);
        
        if (!$kitchen) {
            return ['success' => false, 'message' => 'Kitchen not found'];
        }
        
        // Use branch_id from kitchen if not provided
        if (empty($branch_id) && !empty($kitchen['branch_id'])) {
            $branch_id = intval($kitchen['branch_id']);
        }
        
        // Get printer IP - prioritize branch-specific printer
        $printer_ip = null;
        $printer_port = 9100;
        
        if (!empty($kitchen['ip_address'])) {
            // Direct IP from joined printer table
            $printer_ip = $kitchen['ip_address'];
            $printer_port = !empty($kitchen['port']) ? intval($kitchen['port']) : 9100;
        } elseif (!empty($kitchen['printer'])) {
            if (is_numeric($kitchen['printer'])) {
                $printer_id = intval($kitchen['printer']);
                
                // Check if branch_id column exists in printers table
                $has_branch_id_column = false;
                $check_column = mysqli_query($connection, "SHOW COLUMNS FROM printers LIKE 'branch_id'");
                if ($check_column && mysqli_num_rows($check_column) > 0) {
                    $has_branch_id_column = true;
                }
                
                // Try to get branch-specific printer first, then fallback to any printer with this ID
                if ($branch_id > 0 && $has_branch_id_column) {
                    $printer_query = "SELECT ip_address, port, connection_type, branch_id 
                                     FROM printers 
                                     WHERE printer_id = ? 
                                     AND connection_type = 'network' 
                                     AND (branch_id = ? OR branch_id IS NULL)
                                     ORDER BY branch_id DESC
                                     LIMIT 1";
                    $printer_stmt = mysqli_prepare($connection, $printer_query);
                    if ($printer_stmt) {
                        mysqli_stmt_bind_param($printer_stmt, "ii", $printer_id, $branch_id);
                        if (mysqli_stmt_execute($printer_stmt)) {
                            $printer_result = mysqli_stmt_get_result($printer_stmt);
                            $printer_data = mysqli_fetch_assoc($printer_result);
                            if ($printer_data && !empty($printer_data['ip_address'])) {
                                $printer_ip = $printer_data['ip_address'];
                                $printer_port = !empty($printer_data['port']) ? intval($printer_data['port']) : 9100;
                            }
                        }
                        mysqli_stmt_close($printer_stmt);
                    }
                }
                
                // Fallback: get any printer with this ID if branch-specific not found or branch_id column doesn't exist
                if (empty($printer_ip)) {
                    $printer_query = "SELECT ip_address, port, connection_type FROM printers WHERE printer_id = ? AND connection_type = 'network'";
                    $printer_stmt = mysqli_prepare($connection, $printer_query);
                    if ($printer_stmt) {
                        mysqli_stmt_bind_param($printer_stmt, "i", $printer_id);
                        if (mysqli_stmt_execute($printer_stmt)) {
                            $printer_result = mysqli_stmt_get_result($printer_stmt);
                            $printer_data = mysqli_fetch_assoc($printer_result);
                            if ($printer_data && !empty($printer_data['ip_address'])) {
                                $printer_ip = $printer_data['ip_address'];
                                $printer_port = !empty($printer_data['port']) ? intval($printer_data['port']) : 9100;
                            }
                        }
                        mysqli_stmt_close($printer_stmt);
                    }
                }
            } elseif (filter_var($kitchen['printer'], FILTER_VALIDATE_IP)) {
                // Direct IP address stored in kitchen.printer field
                $printer_ip = $kitchen['printer'];
            }
        }
        
        if (empty($printer_ip)) {
            return ['success' => false, 'message' => 'Printer IP not configured for kitchen: ' . $kitchen['title'] . ' (ID: ' . $kitchen_id . ')'];
        }
        
        // Get order details
        $order_sql = "SELECT o.order_id, o.order_type, o.table_id, o.hall_id, o.comments, o.created_at, o.branch_id, o.terminal,
                             t.table_number, h.name as hall_name, b.branch_name
                      FROM orders o
                      LEFT JOIN tables t ON o.table_id = t.table_id AND o.branch_id = t.branch_id AND o.terminal = t.terminal
                      LEFT JOIN halls h ON o.hall_id = h.hall_id
                      LEFT JOIN branches b ON o.branch_id = b.branch_id
                      WHERE o.order_id = ?";
        
        $order_stmt = mysqli_prepare($connection, $order_sql);
        if (!$order_stmt) {
            return ['success' => false, 'message' => 'Error preparing order query: ' . mysqli_error($connection)];
        }
        
        mysqli_stmt_bind_param($order_stmt, "i", $order_id);
        mysqli_stmt_execute($order_stmt);
        $order_result = mysqli_stmt_get_result($order_stmt);
        $order = mysqli_fetch_assoc($order_result);
        mysqli_stmt_close($order_stmt);
        
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        // Get items for this kitchen
        $items = [];
        $items_sql = "SELECT oik.dish_name, oik.quantity, oik.price, oik.notes, c.name as category_name
                      FROM order_items_kitchen oik
                      LEFT JOIN dishes d ON oik.dish_id = d.dish_id
                      LEFT JOIN categories c ON d.category_id = c.category_id AND d.branch_id = c.branch_id AND d.terminal = c.terminal
                      WHERE oik.order_id = ? AND oik.kitchen_id = ?";
        
        if (!empty($branch_id)) {
            $items_sql .= " AND oik.branch_id = ?";
        }
        
        $items_sql .= " ORDER BY oik.created_at ASC";
        
        $items_stmt = mysqli_prepare($connection, $items_sql);
        if ($items_stmt) {
            if (!empty($branch_id)) {
                mysqli_stmt_bind_param($items_stmt, "iii", $order_id, $kitchen_id, $branch_id);
            } else {
                mysqli_stmt_bind_param($items_stmt, "ii", $order_id, $kitchen_id);
            }
            
            if (mysqli_stmt_execute($items_stmt)) {
                $items_result = mysqli_stmt_get_result($items_stmt);
                while ($item = mysqli_fetch_assoc($items_result)) {
                    $items[] = $item;
                }
            }
            mysqli_stmt_close($items_stmt);
        }
        
        // Fallback to order_items if no items found
        if (empty($items)) {
            $items_sql2 = "SELECT d.name as dish_name, oi.quantity, oi.price, oi.notes, c.name as category_name
                          FROM order_items oi
                          INNER JOIN dishes d ON oi.dish_id = d.dish_id
                          INNER JOIN categories c ON d.category_id = c.category_id AND d.branch_id = c.branch_id AND d.terminal = c.terminal
                          WHERE oi.order_id = ? AND c.kid = ?";
            
            if (!empty($branch_id)) {
                $items_sql2 .= " AND oi.branch_id = ?";
            }
            
            $items_sql2 .= " ORDER BY oi.created_at ASC";
            
            $items_stmt2 = mysqli_prepare($connection, $items_sql2);
            if ($items_stmt2) {
                if (!empty($branch_id)) {
                    mysqli_stmt_bind_param($items_stmt2, "iii", $order_id, $kitchen_id, $branch_id);
                } else {
                    mysqli_stmt_bind_param($items_stmt2, "ii", $order_id, $kitchen_id);
                }
                
                if (mysqli_stmt_execute($items_stmt2)) {
                    $items_result2 = mysqli_stmt_get_result($items_stmt2);
                    while ($item = mysqli_fetch_assoc($items_result2)) {
                        $items[] = $item;
                    }
                }
                mysqli_stmt_close($items_stmt2);
            }
        }
        
        if (empty($items)) {
            return ['success' => true, 'message' => 'No items for this kitchen', 'skipped' => true];
        }
        
        // Build KOT receipt
        $receipt = chr(27) . chr(64); // Initialize printer
        $receipt .= chr(27) . chr(33) . chr(56); // Double height and width
        $receipt .= chr(27) . chr(69) . chr(1); // Bold
        $receipt .= chr(27) . chr(97) . chr(1); // Center alignment
        $receipt .= "KITCHEN ORDER TICKET\n";
        $receipt .= chr(27) . chr(33) . chr(0); // Reset font size
        $receipt .= chr(27) . chr(69) . chr(0); // Disable bold
        
        $receipt .= chr(27) . chr(97) . chr(1); // Center
        $receipt .= chr(27) . chr(69) . chr(1); // Bold
        $receipt .= strtoupper($kitchen['title']) . "\n";
        $receipt .= chr(27) . chr(69) . chr(0); // Disable bold
        $receipt .= "--------------------------------\n";
        
        $receipt .= chr(27) . chr(97) . chr(0); // Left alignment
        $order_date = date('d/m/Y H:i:s', strtotime($order['created_at']));
        $order_number = "ORD-" . $order['order_id'];
        
        $receipt .= "Date: $order_date\n";
        $receipt .= "Order #: $order_number\n";
        $receipt .= "Type: " . ($order['order_type'] ?? 'Dine In') . "\n";
        
        if (!empty($order['table_number'])) {
            $receipt .= "Table: " . $order['table_number'] . "\n";
        }
        
        if (!empty($order['hall_name'])) {
            $receipt .= "Hall: " . $order['hall_name'] . "\n";
        }
        
        $receipt .= "--------------------------------\n";
        $receipt .= chr(27) . chr(69) . chr(1); // Bold
        $receipt .= sprintf("%-20s %5s\n", "ITEM", "QTY");
        $receipt .= chr(27) . chr(69) . chr(0); // Disable bold
        $receipt .= "--------------------------------\n";
        
        foreach ($items as $item) {
            $dish_name = $item['dish_name'] ?? 'Unknown';
            $quantity = intval($item['quantity'] ?? 0);
            
            if (strlen($dish_name) > 20) {
                $dish_name = substr($dish_name, 0, 17) . '...';
            }
            
            $receipt .= sprintf("%-20s %5d\n", $dish_name, $quantity);
            
            if (!empty($item['notes'])) {
                $receipt .= "  Note: " . substr($item['notes'], 0, 30) . "\n";
            }
        }
        
        $receipt .= "--------------------------------\n";
        $receipt .= sprintf("%-20s %5d\n", "TOTAL ITEMS:", count($items));
        
        if (!empty($order['comments'])) {
            $receipt .= "\n";
            $receipt .= "Comments: " . substr($order['comments'], 0, 40) . "\n";
        }
        
        $receipt .= "\n";
        $receipt .= chr(27) . chr(97) . chr(1); // Center
        $receipt .= chr(27) . chr(69) . chr(1); // Bold
        $receipt .= "PLEASE PREPARE\n";
        $receipt .= chr(27) . chr(69) . chr(0); // Disable bold
        $receipt .= "\n\n\n\n";
        $receipt .= chr(29) . chr(86) . chr(1); // Full cut
        
        // Send to printer with improved connection handling
        $socket = false;
        $connection_error = '';
        $last_errno = 0;
        $last_errstr = '';
        
        // Try to connect with longer timeout (5 seconds)
        $socket = @fsockopen($printer_ip, $printer_port, $errno, $errstr, 5);
        $last_errno = $errno;
        $last_errstr = $errstr;
        
        if ($socket === false) {
            // Try alternative ports if default fails
            $alternative_ports = [9100, 515, 631, 9101, 9102];
            foreach ($alternative_ports as $alt_port) {
                if ($alt_port == $printer_port) continue;
                
                $socket = @fsockopen($printer_ip, $alt_port, $errno, $errstr, 3);
                if ($socket !== false) {
                    $printer_port = $alt_port;
                    break;
                } else {
                    // Keep track of the last error
                    $last_errno = $errno;
                    $last_errstr = $errstr;
                }
            }
        }
        
        if ($socket !== false) {
            // Set socket timeout for read/write operations (10 seconds)
            stream_set_timeout($socket, 10);
            
            // Enable blocking mode for reliable writes
            stream_set_blocking($socket, true);
            
            // Get receipt length
            $receipt_length = strlen($receipt);
            
            // Write data to printer - try direct write first (faster for small receipts)
            $bytes_written = @fwrite($socket, $receipt);
            
            // Check if write was successful
            if ($bytes_written === false) {
                // Check if it's a timeout
                $meta = stream_get_meta_data($socket);
                if ($meta['timed_out']) {
                    $connection_error = 'Write operation timed out - printer may be busy or buffer full';
                } else {
                    $connection_error = 'Failed to write data to printer';
                }
                @fclose($socket);
            } else if ($bytes_written === 0) {
                // Try chunked write if direct write failed
                $bytes_written = 0;
                $chunk_size = 512; // Smaller chunks
                $offset = 0;
                $max_retries = 3;
                $retry_count = 0;
                
                while ($offset < $receipt_length && $retry_count < $max_retries) {
                    $chunk = substr($receipt, $offset, $chunk_size);
                    $chunk_written = @fwrite($socket, $chunk);
                    
                    if ($chunk_written === false) {
                        $meta = stream_get_meta_data($socket);
                        if ($meta['timed_out']) {
                            $retry_count++;
                            if ($retry_count >= $max_retries) {
                                $connection_error = 'Write operation timed out after retries';
                                @fclose($socket);
                                break;
                            }
                            usleep(200000); // Wait 200ms before retry
                            continue;
                        } else {
                            $connection_error = 'Failed to write data chunk to printer';
                            @fclose($socket);
                            break;
                        }
                    } else if ($chunk_written > 0) {
                        $bytes_written += $chunk_written;
                        $offset += $chunk_written;
                        $retry_count = 0; // Reset on success
                    } else {
                        // No bytes written - might be buffer full
                        $retry_count++;
                        if ($retry_count >= $max_retries) {
                            $connection_error = 'Printer buffer may be full or not responding';
                            @fclose($socket);
                            break;
                        }
                        usleep(200000); // Wait 200ms
                    }
                }
                
                if ($bytes_written === 0) {
                    $connection_error = 'No data written to printer (0 bytes) - printer may be busy or buffer full';
                    @fclose($socket);
                } else if ($bytes_written < $receipt_length) {
                    $connection_error = "Only $bytes_written of $receipt_length bytes written - printer may have disconnected";
                    @fclose($socket);
                }
            }
            
            // If write was successful, close connection
            if (empty($connection_error) && $bytes_written > 0 && $bytes_written >= $receipt_length) {
                // Give printer a moment to process before closing
                usleep(100000); // 100ms delay
                @fclose($socket);
                
                return [
                    'success' => true,
                    'message' => 'KOT sent to printer successfully',
                    'printer_ip' => $printer_ip,
                    'printer_port' => $printer_port,
                    'kitchen_name' => $kitchen['title'],
                    'order_number' => $order_number,
                    'items_count' => count($items),
                    'bytes_written' => $bytes_written,
                    'total_bytes' => $receipt_length
                ];
            }
        }
        
        // If we get here, connection failed
        $error_message = '';
        if (!empty($connection_error)) {
            $error_message = $connection_error;
        } else {
            // Format error message based on error code
            switch ($last_errno) {
                case 110:
                    $error_message = "Connection timed out - Printer at $printer_ip:$printer_port may be offline or unreachable. Please check: 1) Printer is powered on, 2) Network cable is connected, 3) Printer IP is correct, 4) Firewall allows connections on port $printer_port";
                    break;
                case 111:
                    $error_message = "Connection refused - Printer at $printer_ip:$printer_port is not accepting connections. Check if printer is online and port $printer_port is open.";
                    break;
                case 113:
                    $error_message = "No route to host - Cannot reach printer at $printer_ip:$printer_port. Check network connectivity and IP address.";
                    break;
                default:
                    $error_message = "Could not connect to printer $printer_ip:$printer_port - $last_errstr (Error code: $last_errno). Please verify: 1) Printer IP address is correct ($printer_ip), 2) Printer is powered on and connected to network, 3) Port $printer_port is not blocked by firewall.";
            }
        }
        
        // Ensure error_message is always a string
        if (empty($error_message) || !is_string($error_message)) {
            $error_message = "Could not connect to printer $printer_ip:$printer_port";
        }
        
        return [
            'success' => false,
            'message' => (string)$error_message, // Ensure it's always a string
            'printer_ip' => $printer_ip ?? '',
            'printer_port' => $printer_port ?? 9100,
            'error_code' => $last_errno ?? 0,
            'error_string' => (string)($last_errstr ?? ''),
            'kitchen_name' => $kitchen['title'] ?? '',
            'kitchen_id' => $kitchen_id,
            'troubleshooting' => [
                '1' => 'Verify printer is powered on',
                '2' => 'Check network cable connection',
                '3' => 'Ping printer IP: ping ' . ($printer_ip ?? 'N/A'),
                '4' => 'Verify IP address in printer settings',
                '5' => 'Check firewall allows port ' . ($printer_port ?? 9100),
                '6' => 'Try accessing printer web interface: http://' . ($printer_ip ?? 'N/A')
            ]
        ];
        
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
        return [
            'success' => false, 
            'message' => is_string($error_msg) ? $error_msg : 'An error occurred while printing'
        ];
    } catch (Error $e) {
        $error_msg = $e->getMessage();
        return [
            'success' => false, 
            'message' => is_string($error_msg) ? $error_msg : 'A fatal error occurred while printing'
        ];
    }
}

