<?php
    require_once 'cors_headers.php';
    include("config.php");

    $terminal = $_POST["terminal"];
    $catid = $_POST["catid"];

    $sql = "select * from dishes where terminal = '$terminal' and category_id = '$catid'";
    $result = mysqli_query($connection, $sql) or die("Error in Selecting " . mysqli_error($connection));

    //create an array
    $emparray = array();
    while($row =mysqli_fetch_assoc($result))
    {
        $emparray[] = $row;
    }
    echo json_encode($emparray);

    //close the db connection
    mysqli_close($connection);
?>