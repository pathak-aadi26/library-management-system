<?php
    $host = "localhost";
    $username ="root";
    $password="";
    $database ="member_db";
    $conn = mysqli_connect($host, $username ,$password,$database);
    if ($conn->connect_error)  {
      die("connection failed:" .$conn->connect_error);
    }
  ?>