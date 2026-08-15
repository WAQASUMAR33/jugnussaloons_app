<?php
require_once '../api/cors_headers.php';
include_once "config.php"; // Safer initialization

// Get POST data
$item_id = $_POST["item_id"] ?? null;
$quantity = $_POST["quantity"] ?? null;
$sts = $_POST["sts"] ?? "0"; 

$order_id = $_POST["order_id"] ?? null;
$dish_id = $_POST["dish_id"] ?? null; 

// 1. Validate required fields before starting anything resource-heavy
if (empty($quantity) || empty($order_id) || empty($dish_id)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields: quantity, order_id, and dish_id are required.',
    ]);
    exit;
}

// 2. Resource check
if (!$connection) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection offline.']);
    exit;
}

try {
    $current_date = date("Y-m-d H:i:s");

    // Start transaction
    $connection->begin_transaction();

    // Step 1: Get the price from the dishes table
    $sql_select_dish = "SELECT price FROM dishes WHERE dish_id = ?";
    $stmt_select_dish = $connection->prepare($sql_select_dish);
    if ($stmt_select_dish === false) {
        throw new Exception("Error preparing select dish statement: " . $connection->error);
    }
    $stmt_select_dish->bind_param("i", $dish_id);
    $stmt_select_dish->execute();
    $result_dish = $stmt_select_dish->get_result();

    if ($result_dish->num_rows === 0) {
        throw new Exception("Dish with dish_id $dish_id not found.");
    }

    $row_dish = $result_dish->fetch_assoc();
    $price = $row_dish['price'];
    $stmt_select_dish->close();

    // Step 2: Check if item_id exists in order_items table
    if ($item_id) {
        $sql_select = "SELECT quantity, price, order_id, dish_id FROM order_items WHERE item_id = ?";
        $stmt_select = $connection->prepare($sql_select);
        if ($stmt_select === false) {
            throw new Exception("Error preparing select statement: " . $connection->error);
        }
        $stmt_select->bind_param("i", $item_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Item with item_id $item_id not found.");
        }

        $row = $result->fetch_assoc();
        $old_quantity = $row['quantity'];
        $stmt_select->close(); // Close early to free memory

        // Case A: Add Item Quantity (sts == 0)
        if ($sts == "0") {
            $new_quantity = $old_quantity + $quantity;
            $total_amount = $new_quantity * $price;

            $sql_update = "UPDATE order_items SET quantity = ?, total_amount = ?, updated_at = ? WHERE item_id = ?";
            $stmt_update = $connection->prepare($sql_update);
            if ($stmt_update === false) {
                throw new Exception("Error preparing update statement: " . $connection->error);
            }
            // FIXED: Type bind changed from "idis" to "idss" because $current_date is a string
            $stmt_update->bind_param("idss", $new_quantity, $total_amount, $current_date, $item_id);
            $stmt_update->execute();
            $stmt_update->close();
            
        // Case B: Cancel/Subtract Item Quantity (sts == 1)
        } elseif ($sts == "1") {
            $new_quantity = $old_quantity - $quantity;
            $total_amount = $new_quantity * $price;

            // If quantity goes to 0 or negative, wipe out the main record
            if ($new_quantity <= 0) {
                $sql_delete = "DELETE FROM order_items WHERE item_id = ?";
                $stmt_delete = $connection->prepare($sql_delete);
                if ($stmt_delete === false) {
                    throw new Exception("Error preparing delete statement: " . $connection->error);
                }
                $stmt_delete->bind_param("i", $item_id);
                $stmt_delete->execute();
                $stmt_delete->close();
            }

            // Create tracking entry for the canceled item
            $total_amount_new = $quantity * $price;
            $sql_insert_cancel = "INSERT INTO order_items (order_id, dish_id, quantity, price, total_amount, is_cancel, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert_cancel = $connection->prepare($sql_insert_cancel);
            if ($stmt_insert_cancel === false) {
                throw new Exception("Error preparing cancel insert statement: " . $connection->error);
            }
            $is_cancel = 1; 
            $stmt_insert_cancel->bind_param(
                "iiididss",
                $order_id,
                $dish_id,
                $quantity,
                $price,
                $total_amount_new,
                $is_cancel,
                $current_date,
                $current_date
            );
            $stmt_insert_cancel->execute();
            $stmt_insert_cancel->close();

            // If any quantity is left behind, update the original record
            if ($new_quantity > 0) {
                $sql_update = "UPDATE order_items SET quantity = ?, total_amount = ?, updated_at = ? WHERE item_id = ?";
                $stmt_update = $connection->prepare($sql_update);
                if ($stmt_update === false) {
                    throw new Exception("Error preparing update statement: " . $connection->error);
                }
                // FIXED: Type definition fixed to "idss"
                $stmt_update->bind_param("idss", $new_quantity, $total_amount, $current_date, $item_id);
                $stmt_update->execute();
                $stmt_update->close();
            }
        } else {
            throw new Exception("Invalid sts value: $sts.");
        }
    } else {
        // Case C: Fresh Row Insertion
        $total_amount = $quantity * $price;
        $sql_insert = "INSERT INTO order_items (order_id, dish_id, quantity, price, total_amount, is_cancel, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $connection->prepare($sql_insert);
        if ($stmt_insert === false) {
            throw new Exception("Error preparing insert statement: " . $connection->error);
        }
        $is_cancel = 0; 
        $stmt_insert->bind_param(
            "iiididss",
            $order_id,
            $dish_id,
            $quantity,
            $price,
            $total_amount,
            $is_cancel,
            $current_date,
            $current_date
        );
        $stmt_insert->execute();
        
        if ($stmt_insert->affected_rows === 0) {
            throw new Exception("Failed to insert new order_items.");
        }

        $new_item_id = $connection->insert_id;
        $stmt_insert->close();

        // Commit and exit immediately for clean response handling
        $connection->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'New order item inserted successfully.',
            'item_id' => $new_item_id,  
        ]);
        exit;
    }

    // Commit global update states
    $connection->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Order item updated or processed successfully.',
        'item_id' => $item_id,  
    ]);

} catch (Exception $e) {
    // Fail safe roll back connection context if transaction crashes mid-flight
    if ($connection) {
        $connection->rollback();
    }

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
} finally {
    // Explicit close structure keeps the hourly connection footprint small!
    if ($connection instanceof mysqli) {
        $connection->close();
    } else if ($connection) {
        mysqli_close($connection);
    }
}
?>