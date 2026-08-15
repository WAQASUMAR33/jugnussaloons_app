<?php
require_once '../api/cors_headers.php';
include("config.php");

// Set content headers up front
header('Content-Type: application/json; charset=utf-8');

// 1. Enforce POST request verification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. Only POST is permitted.']);
    exit;
}

try {
    // Verify database connection engine instance explicitly
    if (!isset($connection) || $connection->connect_error) {
        throw new Exception("Database engine connection failed.");
    }

    // 2. Fetching POST data safely with fallback assignments
    $pid        = $_POST["pid"] ?? null;
    $des        = $_POST["des"] ?? '';
    $qnty       = isset($_POST["qnty"]) ? intval($_POST["qnty"]) : 0;
    $unitprice  = isset($_POST["unitprice"]) ? floatval($_POST["unitprice"]) : 0.00;
    $totalprice = isset($_POST["totalprice"]) ? floatval($_POST["totalprice"]) : 0.00;
    $discount   = isset($_POST["discount"]) ? floatval($_POST["discount"]) : 0.00;
    $netTotal   = isset($_POST["netTotal"]) ? floatval($_POST["netTotal"]) : 0.00;
    $orderid    = $_POST["orderid"] ?? null;
    $barcode    = $_POST["barcode"] ?? '';
    $prate      = isset($_POST["prate"]) ? floatval($_POST["prate"]) : 0.00;
    $tprate     = isset($_POST["tprate"]) ? floatval($_POST["tprate"]) : 0.00;
    $terminal   = isset($_POST["terminal"]) ? intval($_POST["terminal"]) : 1;

    // Validate absolute baseline fields
    if (empty($pid) || empty($orderid) || $qnty <= 0) {
        http_response_code(400);
        throw new Exception("Missing required fields: pid, orderid, and a valid quantity are required.");
    }

    $current_date = date("Y-m-d H:i:s");

    // 3. Begin Transaction to preserve complete atomic consistency
    $connection->begin_transaction();

    // Query A: Insert new line items using a secure prepared statement
    $insertSql = "INSERT INTO orderdetails 
                  (pid, des, qnty, unitprice, totalprice, discount, netTotal, orderid, barcode, prate, tprate, terminal, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $connection->prepare($insertSql);
    if (!$stmt) {
        throw new Exception("Failed preparing order storage framework.");
    }

    $stmt->bind_param("ssiddddisddiss", $pid, $des, $qnty, $unitprice, $totalprice, $discount, $netTotal, $orderid, $barcode, $prate, $tprate, $terminal, $current_date, $current_date);
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Failed writing granular order line data items.");
    }
    $stmt->close();

    // Query B: Get old product inventory metrics with an explicit ROW LOCK (FOR UPDATE)
    $prodSql = "SELECT qnty FROM products WHERE id = ? FOR UPDATE";
    $pStmt = $connection->prepare($prodSql);
    if (!$pStmt) {
        throw new Exception("Failed preparing system product verification criteria.");
    }

    $pStmt->bind_param("s", $pid);
    $pStmt->execute();
    $pResult = $pStmt->get_result();

    if ($pResult->num_rows === 0) {
        $pStmt->close();
        throw new Exception("Target item identifier does not match active inventory rows.");
    }

    $row = $pResult->fetch_assoc();
    $old_qnty = intval($row["qnty"]);
    $pStmt->close();

    // Calculate new operational parameters safely
    $new_qnty = $old_qnty - $qnty;

    // Query C: Apply updating parameters onto inventory tracking matrices
    $updateSql = "UPDATE products SET qnty = ?, updated_at = ? WHERE id = ?";
    $uStmt = $connection->prepare($updateSql);
    if (!$uStmt) {
        throw new Exception("Failed preparing system matrix update framework.");
    }

    $uStmt->bind_param("iss", $new_qnty, $current_date, $pid);
    if (!$uStmt->execute()) {
        $uStmt->close();
        throw new Exception("Failed finalizing system asset matrix parameters.");
    }
    $uStmt->close();

    // 4. Everything processed perfectly. Commit the changes together!
    $connection->commit();

    // Respond with one clean, unified success payload
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Order details documented and active warehouse counts decremented successfully.'
    ]);

} catch (Throwable $e) {
    // 5. Rollback on failure to prevent partial writes
    if (isset($connection) && $connection->ping()) {
        $connection->rollback();
    }
    
    error_log("Inventory Reconciliation Error: " . $e->getMessage());
    
    // Scrub detailed system error responses for client output safety
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
?>