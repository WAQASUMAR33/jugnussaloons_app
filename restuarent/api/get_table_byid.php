<?php
    
   require_once 'cors_headers.php';
    include("config.php");
    
    
    $terminal_id = $_POST["terminal"];
    $hall_id = $_POST["hall_id"];
    
    $sql = "select tables.*,halls.name from  tables inner join halls on halls.hall_id = tables.hall_id  where halls.hall_id = '$hall_id' and tables.terminal = ".$terminal_id;
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