<?php
require_once '../api/cors_headers.php';
include_once "config.php"; // Prevents multiple allocation runs

// 1. Force POST-only execution
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

// 2. Safely capture data with fallback values to avoid undefined index errors
$totalamount      = $_POST["totalamount"] ?? '0';
$supid            = $_POST["supid"] ?? null;
$prebal           = $_POST["prebal"] ?? '0';
$payment          = $_POST["payment"] ?? '0';
$balance          = $_POST["balance"] ?? '0';
$genby            = $_POST["genby"] ?? '';
$paymode          = $_POST["paymode"] ?? '';
$details          = $_POST["details"] ?? '';
$invoiceno        = $_POST["invoiceno"] ?? '';
$total_gst        = $_POST["total_gst"] ?? '0';
$net_total_amount = $_POST["net_total_amount"] ?? '0';
$terminal         = $_POST["terminal"] ?? '';

// Basic structural validation
if (empty($supid) || empty($invoiceno)) {
    echo json_encode(["status" => "error", "message" => "Supplier ID and Invoice Number are mandatory fields."]);
    exit;
}

// 3. Structural Link verification
if (!$connection) {
    echo json_encode(["status" => "error", "message" => "Database node unreachable."]);
    exit;
}

try {
    $current_date = date("Y-m-d H:i:s");

    // Begin ACID Safe Transaction 
    $connection->begin_transaction();
    
    // Step 1: Securely insert record into invoice table using Prepared Statements
    $sqlInvoice = "INSERT INTO invoice (totalamount, suid, prebal, payment, balance, genby, paymode, details, invoiceno, total_gst, net_total_amount, terminal, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtInvoice = $connection->prepare($sqlInvoice);
    if (!$stmtInvoice) {
        throw new Exception("Invoice statement prep failed: " . $connection->error);
    }
    
    // Bind the parameters (s = string, d = double/decimal, i = integer)
    // Adjust types depending on your column schemas
    $stmtInvoice->bind_param(
        "siddssssssdsss", 
        $totalamount, $supid, $prebal, $payment, $balance, 
        $genby, $paymode, $details, $invoiceno, $total_gst, 
        $net_total_amount, $terminal, $current_date, $current_date
    );
    
    if (!$stmtInvoice->execute()) {
        throw new Exception("Invoice entry execution dropped: " . $stmtInvoice->error);
    }
    $last_id = $connection->insert_id;
    $stmtInvoice->close();

    // Step 2: Fetch the current balance of the supplier safely
    $sqlSupplier = "SELECT balance FROM suppliers WHERE id = ?";
    $stmtSupplier = $connection->prepare($sqlSupplier);
    if (!$stmtSupplier) {
        throw new Exception("Supplier look up schema error: " . $connection->error);
    }
    $stmtSupplier->bind_param("i", $supid);
    $stmtSupplier->execute();
    $supplierResult = $stmtSupplier->get_result();

    if ($supplierResult->num_rows === 0) {
        throw new Exception('Target supplier matching account not found.');
    }

    $row = $supplierResult->fetch_assoc();
    $old_balance = $row["balance"];
    $stmtSupplier->close();

    // Calculate balance values matching corporate definitions safely
    $new_balance = ($old_balance + $net_total_amount) - $payment;

    // Step 3: Update the supplier's balance records
    $sqlUpdateSup = "UPDATE suppliers SET balance = ?, updated_at = ? WHERE id = ?";
    $stmtUpdateSup = $connection->prepare($sqlUpdateSup);
    if (!$stmtUpdateSup) {
        throw new Exception("Supplier allocation compilation failure: " . $connection->error);
    }
    $stmtUpdateSup->bind_param("dsi", $new_balance, $current_date, $supid);
    if (!$stmtUpdateSup->execute()) {
        throw new Exception("Balance assignment rejected: " . $stmtUpdateSup->error);
    }
    $stmtUpdateSup->close();

    // Step 4: Record history track inside transaction register table
    $sqlTrnx = "INSERT INTO suptrnx (supid, prebalance, inamount, outamount, balance, created_at, updated_at, type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Sale')";
    $stmtTrnx = $connection->prepare($sqlTrnx);
    if (!$stmtTrnx) {
        throw new Exception("Transaction logging initialization error: " . $connection->error);
    }
    $stmtTrnx->bind_param("iddddss", $supid, $old_balance, $net_total_amount, $payment, $new_balance, $current_date, $current_date);
    if (!$stmtTrnx->execute()) {
        throw new Exception("Audit trail tracking write operation failed: " . $stmtTrnx->error);
    }
    $stmtTrnx->close();

    // Commit transaction only if all 4 steps succeeded
    $connection->commit();

    // Success response array output context
    echo json_encode(['status' => 'success', 'inserted_id' => $last_id]);

} catch (Exception $e) {
    // If anything fails above, instantly revert every step to prevent broken calculations
    if ($connection) {
        $connection->rollback();
    }
    
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // Cleanup remaining global link dependencies to stay underneath hosting thresholds
    if ($connection instanceof mysqli) {
        $connection->close();
    } else if ($connection) {
        mysqli_close($connection);
    }
}
?>