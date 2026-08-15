<?php
/**
 * Setup Kitchen Printers
 * This script configures printers and links them to kitchens
 * 
 * Run once to set up:
 * http://localhost/restuarent/api/setup_kitchen_printers.php
 */

require_once 'cors_headers.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    include("config.php");
    
    if (!isset($connection) || !$connection) {
        throw new Exception("Database connection failed");
    }
    
    $results = [];
    
    // Step 1: Ensure printers table exists
    $check_printers = mysqli_query($connection, "SHOW TABLES LIKE 'printers'");
    if (mysqli_num_rows($check_printers) == 0) {
        $create_printers = "CREATE TABLE IF NOT EXISTS printers (
            printer_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            ip_address VARCHAR(50) NOT NULL,
            port INT NOT NULL DEFAULT 9100,
            type VARCHAR(50) NOT NULL DEFAULT 'kitchen',
            terminal INT NOT NULL DEFAULT 1,
            branch_id INT DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (mysqli_query($connection, $create_printers)) {
            $results[] = "✅ Printers table created";
        } else {
            throw new Exception("Failed to create printers table: " . mysqli_error($connection));
        }
    } else {
        $results[] = "✅ Printers table exists";
    }
    
    // Step 2: Insert or update printers
    $printers = [
        [
            'name' => 'Fast Food Kitchen Printer',
            'ip_address' => '192.168.1.101',
            'port' => 9100,
            'type' => 'kitchen',
            'terminal' => 1
        ],
        [
            'name' => 'BBQ Kitchen Printer',
            'ip_address' => '192.168.1.102',
            'port' => 9100,
            'type' => 'kitchen',
            'terminal' => 1
        ]
    ];
    
    foreach ($printers as $printer) {
        // Check if printer exists
        $check_sql = "SELECT printer_id FROM printers WHERE ip_address = ?";
        $check_stmt = mysqli_prepare($connection, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $printer['ip_address']);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $existing = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);
        
        if ($existing) {
            // Update existing
            $update_sql = "UPDATE printers SET name = ?, port = ?, type = ?, terminal = ?, updated_at = NOW() WHERE printer_id = ?";
            $update_stmt = mysqli_prepare($connection, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sissi", 
                $printer['name'], 
                $printer['port'], 
                $printer['type'], 
                $printer['terminal'],
                $existing['printer_id']
            );
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
            $results[] = "✅ Updated printer: " . $printer['name'] . " (" . $printer['ip_address'] . ")";
        } else {
            // Insert new
            $insert_sql = "INSERT INTO printers (name, ip_address, port, type, terminal, status, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())";
            $insert_stmt = mysqli_prepare($connection, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "ssisi", 
                $printer['name'], 
                $printer['ip_address'], 
                $printer['port'], 
                $printer['type'], 
                $printer['terminal']
            );
            mysqli_stmt_execute($insert_stmt);
            $printer_id = mysqli_insert_id($connection);
            mysqli_stmt_close($insert_stmt);
            $results[] = "✅ Created printer: " . $printer['name'] . " (ID: $printer_id)";
        }
    }
    
    // Step 3: Link kitchens to printers
    $kitchen_mappings = [
        ['kitchen_name' => 'Fast Food', 'printer_ip' => '192.168.1.101'],
        ['kitchen_name' => 'BBQ', 'printer_ip' => '192.168.1.102']
    ];
    
    foreach ($kitchen_mappings as $mapping) {
        // Get printer_id
        $printer_sql = "SELECT printer_id FROM printers WHERE ip_address = ?";
        $printer_stmt = mysqli_prepare($connection, $printer_sql);
        mysqli_stmt_bind_param($printer_stmt, "s", $mapping['printer_ip']);
        mysqli_stmt_execute($printer_stmt);
        $printer_result = mysqli_stmt_get_result($printer_stmt);
        $printer_data = mysqli_fetch_assoc($printer_result);
        mysqli_stmt_close($printer_stmt);
        
        if ($printer_data) {
            $printer_id = $printer_data['printer_id'];
            
            // Update kitchens that match the name
            $update_kitchen_sql = "UPDATE kitchens SET printer = ? WHERE title LIKE ? OR code LIKE ?";
            $kitchen_pattern = '%' . $mapping['kitchen_name'] . '%';
            $update_kitchen_stmt = mysqli_prepare($connection, $update_kitchen_sql);
            mysqli_stmt_bind_param($update_kitchen_stmt, "iss", $printer_id, $kitchen_pattern, $kitchen_pattern);
            mysqli_stmt_execute($update_kitchen_stmt);
            $affected = mysqli_stmt_affected_rows($update_kitchen_stmt);
            mysqli_stmt_close($update_kitchen_stmt);
            
            if ($affected > 0) {
                $results[] = "✅ Linked " . $mapping['kitchen_name'] . " kitchen to printer " . $mapping['printer_ip'] . " ($affected kitchens updated)";
            } else {
                $results[] = "⚠️ No kitchens found matching: " . $mapping['kitchen_name'];
            }
        }
    }
    
    // Step 4: Show current configuration
    $config_sql = "SELECT k.kitchen_id, k.title, k.code, k.printer, 
                          p.name as printer_name, p.ip_address, p.port
                   FROM kitchens k
                   LEFT JOIN printers p ON k.printer = p.printer_id
                   ORDER BY k.kitchen_id";
    $config_result = mysqli_query($connection, $config_sql);
    
    $kitchen_config = [];
    while ($row = mysqli_fetch_assoc($config_result)) {
        $kitchen_config[] = [
            'kitchen_id' => $row['kitchen_id'],
            'kitchen_name' => $row['title'],
            'printer_linked' => !empty($row['printer']),
            'printer_name' => $row['printer_name'] ?? 'Not linked',
            'printer_ip' => $row['ip_address'] ?? 'Not configured'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Kitchen printer setup completed',
        'results' => $results,
        'kitchen_config' => $kitchen_config
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

exit();
?>

