<?php
require_once 'cors_headers.php';
include("config.php");

// Settings
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// ========================================
// 1. READ 'sts' FROM GET PARAMETER
// ========================================
$sts = isset($_GET['sts']) ? trim($_GET['sts']) : null;

if ($sts === null || $sts === '') {
    echo json_encode([
        "success" => false,
        "message" => "Parameter 'sts' is required"
    ]);
    exit;
}

// Validate that sts is a valid positive integer
if (!ctype_digit($sts)) {
    echo json_encode([
        "success" => false,
        "message" => "'sts' must be a valid positive integer"
    ]);
    exit;
}

$sts = (int)$sts;

// Optional: restrict to allowed statuses (extra security)


// ========================================
// 2. FETCH ORDERS FROM DATABASE
// ========================================
try {
    // Check connection (assuming $connection comes from config.php)
    if (!$connection || mysqli_connect_errno()) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }

    $sql = "SELECT
                order_id,
                branch_id,
                hall_id,
                table_id,
                order_type,
                g_total_amount,
                discount_amount,
                service_charge,
                net_total_amount,
                payment_mode,
                created_at
            FROM orders
            WHERE sts = ?
            ORDER BY created_at DESC";

    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($connection));
    }

    mysqli_stmt_bind_param($stmt, "i", $sts);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = [
            "order_id"        => (int)$row["order_id"],
            "branch_id"       => (int)$row["branch_id"],
            "hall_id"         => (int)$row["hall_id"],
            "table_id"        => (int)$row["table_id"],
            "order_type"      => $row["order_type"],
            "g_total_amount"  => (float)$row["g_total_amount"],
            "discount_amount" => (float)$row["discount_amount"],
            "service_charge"  => (float)$row["service_charge"],
            "net_total_amount"=> (float)$row["net_total_amount"],
            "payment_mode"   => $row["payment_mode"],         // usually string (e.g., 'cash', 'card')
            "created_at"      => $row["created_at"]
        ];
    }

    mysqli_stmt_close($stmt);

    // Success response
    echo json_encode([
        "success" => true,
        "sts"     => $sts,
        "count"   => count($orders),
        "data"    => $orders
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("GET Orders API Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch orders"
        // "debug" => $e->getMessage()  // Uncomment only for development
    ]);
}

exit();
?>