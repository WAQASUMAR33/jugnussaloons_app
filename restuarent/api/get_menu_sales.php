<?php
/**
 * Get Menu Sales API (GET)
 * Filters data by orders.sts
 * Input: sts as query parameter
 */

require_once 'cors_headers.php';
include("config.php");

header("Content-Type: application/json; charset=UTF-8");

ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ----------------------------
   DB CONNECTION CHECK
-----------------------------*/
if (!isset($connection) || !$connection) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit();
}

/* ----------------------------
   READ INPUT (GET)
-----------------------------*/
$sts = isset($_GET['sts']) ? intval($_GET['sts']) : null;

if ($sts === null) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "sts parameter is required"
    ]);
    exit();
}

/* ----------------------------
   SQL QUERY (NO implode)
-----------------------------*/
$sql = "
SELECT 
    d.dish_id,
    d.name AS name,
    d.category_id,
    COALESCE(c.name, 'Uncategorized') AS category_name,
    COALESCE(SUM(oi.quantity), 0) AS quantity_sold,
    COALESCE(SUM(oi.total_amount), 0) AS total_revenue
FROM dishes d
LEFT JOIN categories c 
    ON c.category_id = d.category_id 
    AND c.branch_id = d.branch_id 
    AND c.terminal = d.terminal
INNER JOIN order_items oi 
    ON oi.dish_id = d.dish_id
INNER JOIN orders o 
    ON o.order_id = oi.order_id
WHERE o.sts = ?
GROUP BY d.dish_id, d.name, d.category_id, c.name
HAVING quantity_sold > 0
";

/* ----------------------------
   EXECUTE QUERY
-----------------------------*/
$stmt = mysqli_prepare($connection, $sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare query"
    ]);
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $sts);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to execute query"
    ]);
    exit();
}

$result = mysqli_stmt_get_result($stmt);

/* ----------------------------
   FETCH DATA
-----------------------------*/
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        "dish_id" => (int)$row['dish_id'],
        "name" => $row['name'],
        "category_id" => $row['category_id'] ? (int)$row['category_id'] : null,
        "category_name" => $row['category_name'],
        "quantity_sold" => (int)$row['quantity_sold'],
        "total_revenue" => (float)$row['total_revenue']
    ];
}

mysqli_stmt_close($stmt);

/* ----------------------------
   RESPONSE
-----------------------------*/
echo json_encode([
    "success" => true,
    "sts" => $sts,
    "count" => count($data),
    "data" => $data
]);

exit();
?>
