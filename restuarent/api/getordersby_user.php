<?php
require_once 'cors_headers.php';
    include("config.php");

    // Get the POST parameter 'orderid'
    $ot_id = $_POST['ot_id'];

    if (!isset($ot_id) || empty($ot_id)) {
        // If 'orderid' is not provided, return an error message
        echo json_encode(['status' => 'error', 'message' => 'Order ID is required']);
        exit;
    }

    // Modify SQL query to select from 'orders' where 'orderid' matches
    $sql = "SELECT * FROM orders WHERE ot_id = '$ot_id'";

    // Execute the query
    $result = mysqli_query($connection, $sql) or die("Error in Selecting " . mysqli_error($connection));

    // Create an array to hold the response
    $orderArray = array();

    // Fetch the result and store it in the array
    if(mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $orderArray[] = $row;
        }
        // Return the JSON-encoded result
        echo json_encode($orderArray);
    } else {
        // If no record is found, return an empty response with a message
        echo json_encode(['status' => 'error', 'message' => 'No order found for the given Order ID']);
    }

    // Close the database connection
    mysqli_close($connection);
?>
