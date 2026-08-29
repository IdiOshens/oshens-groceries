<?php
function db_connect()
{
    //declaring variables for my server
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "oshens_gloceries";

    //create a variable conn create the connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    //check if connection is success
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
