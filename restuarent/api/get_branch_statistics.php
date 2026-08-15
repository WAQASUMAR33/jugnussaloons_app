<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once 'cors_headers.php';
// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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
        echo json_encode(["success" => false, "message" => "Fatal error: " . $error['message']]);
        exit();
    }
});

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
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "message" => "Configuration error: " . $e->getMessage()]);
    exit();
}


$sql = "
    SELECT 
        b.branch_id,
        b.branch_name,

        SUM(CASE WHEN o.order_status = 'Complete' AND o.sts = 0 THEN 1 ELSE 0 END) AS total_complete_orders,

        SUM(CASE WHEN o.order_status = 'Running' AND o.sts = 0 THEN 1 ELSE 0 END) AS total_running_orders,

        SUM(CASE WHEN o.order_status = 'Complete' AND o.sts = 0 THEN o.net_total_amount ELSE 0 END) AS total_completed_sales

    FROM branches b
    LEFT JOIN orders o 
        ON b.branch_id = o.branch_id
        AND o.sts = 0
";

// If branch_id is passed → filter
if ($branch_id > 0) {
    $sql .= " WHERE b.branch_id = $branch_id ";
}

$sql .= " GROUP BY b.branch_id, b.branch_name ";

// Execute Query
$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        "status" => "error",
        "message" => "Query failed",
        "error_detail" => $conn->error
    ]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $data
]);

$conn->close();
?>
