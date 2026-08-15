<?php
require_once '../api/cors_headers.php';
include("config.php");

// Fetching POST data with default values
$pid = $_POST["pid"] ?? '';
$des = $_POST["des"] ?? '';
$qnty = $_POST["qnty"] ?? 0;
$unitprice = $_POST["unitprice"] ?? 0.0;
$totalprice = $_POST["totalprice"] ?? 0.0;
$discount = $_POST["discount"] ?? 0.0;
$netprice = $_POST["netprice"] ?? 0.0;
$invoiceid = $_POST["invoiceid"] ?? '';
$barcode = $_POST["barcode"] ?? '';
$gst = $_POST["gst"] ?? 0.0;
$netTotal = $_POST["netTotal"] ?? 0.0;
$terminal = $_POST["terminal"] ?? '';

// Get the current date and time for created_at and updated_at fields
$current_date = date("Y-m-d H:i:s");

try {
    // Insert new record into purdetails table
    $sql = "INSERT INTO purdetails (pid, des, qnty, unitprice, totalprice, discount, netprice, invoiceid, barcode, gst, netTotal, terminal, created_at, updated_at) 
            VALUES ('$pid', '$des', '$qnty', '$unitprice', '$totalprice', '$discount', '$netprice', '$invoiceid', '$barcode', '$gst', '$netTotal', '$terminal', '$current_date', '$current_date')";

    if ($connection->query($sql) === TRUE) {
        // Get the last inserted ID
        // Fetch the current quantity of the product from the products table
        $productQuery = "SELECT qnty FROM products WHERE id = '$pid'";
        $productResult = $connection->query($productQuery);

        if ($productResult->num_rows > 0) {
            // Get the old quantity
            $row = $productResult->fetch_assoc();
            $old_qnty = $row["qnty"];
            // Calculate the new quantity
            $new_qnty = $old_qnty + $qnty;

            // Update the product's quantity in the products table
            $updateProduct = "update products SET qnty = '$new_qnty', updated_at = '$current_date' WHERE id = '$pid'";
            if ($connection->query($updateProduct) === TRUE) {
                // Return success response in JSON format with inserted ID
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Purchase details added and product quantity updated successfully'
                ]);
            } else {
                throw new Exception('Error updating product quantity: ' . $connection->error);
            }
        } else {
            throw new Exception('Product not found'+ $pid);
        }
    } else {
        throw new Exception('Error: ' . $sql . '<br>' . $connection->error);
    }
} catch (Exception $e) {
    // Rollback transaction in case of error
    // Return error response with the exception message
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Closing the database connection
$connection->close();
?>
